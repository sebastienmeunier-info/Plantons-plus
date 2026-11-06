#!/usr/bin/env python3
"""Génère le fichier territoire.geojson utilisé par Plantons.

Le territoire peut être composé de plusieurs zones géographiques de types
différents : communes, intercommunalités (communautés de communes, d'agglomé­ration,
métropoles…), départements, régions ou pays. Les limites des zones françaises
sont téléchargées depuis geo.api.gouv.fr, le service public de diffusion du
découpage administratif français ; les pays proviennent de Natural Earth.

Le script n'a aucune dépendance externe : Python 3.9 ou plus récent suffit.

Exemples :

    # Une commune
    python3 scripts/generer-territoire.py --zone commune:49018

    # Une communauté de communes (code EPCI)
    python3 scripts/generer-territoire.py --zone epci:244900015

    # Un département entier
    python3 scripts/generer-territoire.py --zone departement:49

    # Une région entière
    python3 scripts/generer-territoire.py --zone region:52

    # Un pays (code ISO à deux lettres)
    python3 scripts/generer-territoire.py --zone pays:FR

    # Plusieurs zones de types différents, fusionnées en un seul territoire
    python3 scripts/generer-territoire.py \
        --zone commune:49018 --zone commune:49130 \
        --zone epci:244900015 --output data/territoire.geojson

    # Rechercher un code à partir d'un nom
    python3 scripts/generer-territoire.py --recherche commune:Bauge
"""

from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path
from typing import Any
from urllib.parse import urlencode
from urllib.request import Request, urlopen

API_BASE_URL = "https://geo.api.gouv.fr"
NATURAL_EARTH_URL = (
    "https://raw.githubusercontent.com/nvkelso/natural-earth-vector/"
    "master/geojson/ne_50m_admin_0_countries.geojson"
)
USER_AGENT = "Plantons/1.0 (+https://github.com/sebastienmeunier-info/plantons)"

# Types de zones gérés et endpoint geo.api.gouv.fr correspondant.
ZONE_ENDPOINTS: dict[str, str] = {
    "commune": "/communes/{code}",
    "epci": "/epcis/{code}",
    "departement": "/departements/{code}",
    "region": "/regions/{code}",
}
SEARCH_ENDPOINTS: dict[str, str] = {
    "commune": "/communes",
    "epci": "/epcis",
    "departement": "/departements",
    "region": "/regions",
}


def get_json(url: str) -> Any:
    """Télécharge et décode une réponse JSON."""
    request = Request(url, headers={"User-Agent": USER_AGENT})
    try:
        with urlopen(request, timeout=90) as response:
            return json.load(response)
    except OSError as error:
        raise RuntimeError(f"Impossible de télécharger {url} : {error}") from error


def api_get(path: str, parameters: dict[str, Any]) -> Any:
    """Interroge geo.api.gouv.fr avec les paramètres donnés."""
    query = urlencode(parameters, doseq=True)
    url = f"{API_BASE_URL}{path}?{query}" if query else f"{API_BASE_URL}{path}"
    return get_json(url)


def as_features(payload: Any) -> list[dict[str, Any]]:
    """Normalise une réponse GeoJSON Feature ou FeatureCollection."""
    if isinstance(payload, dict) and payload.get("type") == "FeatureCollection":
        features = payload.get("features", [])
        if isinstance(features, list):
            return features
    if isinstance(payload, dict) and payload.get("type") == "Feature":
        return [payload]
    raise RuntimeError("La réponse reçue n'est pas un GeoJSON valide.")


def download_zone(zone_type: str, zone_code: str) -> list[dict[str, Any]]:
    """Télécharge le contour d'une zone et l'étiquette avec son type et son code."""
    if zone_type == "pays":
        payload = get_json(NATURAL_EARTH_URL)
        wanted = zone_code.upper()
        matches = []
        for feature in as_features(payload):
            properties = feature.get("properties", {})
            iso = str(properties.get("ISO_A2_EH") or properties.get("ISO_A2") or "")
            name = str(properties.get("ADMIN") or properties.get("name") or "")
            if iso == wanted or name.upper() == zone_code.upper():
                matches.append(feature)
        if not matches:
            raise RuntimeError(
                f"Pays « {zone_code} » introuvable (utilisez un code ISO à deux lettres, ex. FR)."
            )
        features = matches
    else:
        endpoint = ZONE_ENDPOINTS[zone_type].format(code=zone_code)
        payload = api_get(endpoint, {"format": "geojson", "geometry": "contour"})
        features = as_features(payload)
        if not features:
            raise RuntimeError(f"Aucun contour reçu pour la zone {zone_type}:{zone_code}.")

    for feature in features:
        properties = feature.setdefault("properties", {})
        properties["zone_type"] = zone_type
        properties["zone_code"] = zone_code
    return features


def search_codes(zone_type: str, name: str) -> list[dict[str, Any]]:
    """Recherche les codes des zones dont le nom contient le texte donné."""
    if zone_type not in SEARCH_ENDPOINTS:
        raise RuntimeError(f"La recherche n'est pas disponible pour le type « {zone_type} ».")
    payload = api_get(SEARCH_ENDPOINTS[zone_type], {"nom": name})
    if not isinstance(payload, list):
        raise RuntimeError("La réponse de recherche n'est pas une liste valide.")
    return payload


def write_geojson(path: Path, features: list[dict[str, Any]]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    content = {"type": "FeatureCollection", "features": features}
    path.write_text(json.dumps(content, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def parse_zone(value: str) -> tuple[str, str]:
    """Décompose un argument --zone de la forme type:code."""
    if ":" not in value:
        raise RuntimeError(
            f"Zone « {value} » invalide : format attendu type:code "
            f"(types : {', '.join(sorted(ZONE_ENDPOINTS))}, pays)."
        )
    zone_type, zone_code = value.split(":", 1)
    zone_type = zone_type.strip().lower()
    zone_code = zone_code.strip()
    if zone_type != "pays" and zone_type not in ZONE_ENDPOINTS:
        raise RuntimeError(
            f"Type de zone « {zone_type} » inconnu "
            f"(types : {', '.join(sorted(ZONE_ENDPOINTS))}, pays)."
        )
    if not zone_code:
        raise RuntimeError(f"Code manquant pour la zone « {value} ».")
    return zone_type, zone_code


def parse_arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description=(
            "Génère territoire.geojson en fusionnant les contours de plusieurs "
            "zones géographiques (commune, epci, departement, region, pays)."
        ),
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=__doc__.split("Exemples :", 1)[1] if __doc__ else None,
    )
    parser.add_argument(
        "--zone",
        action="append",
        default=[],
        metavar="TYPE:CODE",
        help="Zone à inclure, répétable. Types : commune, epci, departement, region, pays.",
    )
    parser.add_argument(
        "--output",
        type=Path,
        default=Path("data/territoire.geojson"),
        help="Fichier de destination (par défaut : data/territoire.geojson).",
    )
    parser.add_argument(
        "--recherche",
        metavar="TYPE:NOM",
        help="Recherche les codes des zones dont le nom contient NOM, sans générer de fichier.",
    )
    return parser.parse_args()


def main() -> int:
    arguments = parse_arguments()

    if arguments.recherche:
        try:
            zone_type, name = parse_zone(arguments.recherche)
            results = search_codes(zone_type, name)
        except RuntimeError as error:
            print(f"Erreur : {error}", file=sys.stderr)
            return 1
        for result in results:
            print(f"{result.get('code', '?')}\t{result.get('nom', result.get('libelle', '?'))}")
        return 0

    if not arguments.zone:
        print("Erreur : indiquez au moins une --zone (ex. --zone commune:49018).", file=sys.stderr)
        return 1

    features: list[dict[str, Any]] = []
    try:
        for value in arguments.zone:
            zone_type, zone_code = parse_zone(value)
            downloaded = download_zone(zone_type, zone_code)
            features.extend(downloaded)
            label = downloaded[0].get("properties", {}).get("nom")
            print(f"Zone {zone_type}:{zone_code} — {len(downloaded)} contour(s)" + (f" ({label})" if label else ""))
        write_geojson(arguments.output, features)
    except RuntimeError as error:
        print(f"Erreur : {error}", file=sys.stderr)
        return 1

    print(f"{arguments.output} : {len(features)} zone(s) fusionnée(s).")
    print("Vérifiez ensuite le rendu sur la carte publique de l'application.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
