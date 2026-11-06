# Créer le fichier `territoire.geojson`

Le fichier `data/territoire.geojson` délimite la zone dans laquelle les
contributions (propositions, dons, recensements) sont acceptées. Tout envoi
situé hors de cette zone est refusé. Il alimente aussi l'affichage de la limite
sur les cartes publiques.

## Génération avec le script

Le script `scripts/generer-territoire.py` construit ce fichier à partir de
**plusieurs zones géographiques de types différents**, fusionnées en un seul
territoire. Les limites proviennent de
[geo.api.gouv.fr](https://geo.api.gouv.fr) (service public) pour les zones
françaises, et de Natural Earth pour les pays. Aucune dépendance à installer :
Python 3.9 suffit.

```bash
python3 scripts/generer-territoire.py --zone TYPE:CODE [--zone TYPE:CODE ...] [--output data/territoire.geojson]
```

### Types de zones disponibles

| Type | Description | Exemple de code | Où trouver le code |
|---|---|---|---|
| `commune` | Commune française | `49018` (Baugé-en-Anjou) | Recherche ci-dessous |
| `epci` | Intercommunalité (communauté de communes, d'agglomération, métropole…) | `244900015` | Recherche ci-dessous |
| `departement` | Département | `49` (Maine-et-Loire) | Recherche ci-dessous |
| `region` | Région | `52` (Pays de la Loire) | Recherche ci-dessous |
| `pays` | Pays (code ISO à deux lettres) | `FR`, `BE`, `CH` | Norme ISO 3166-1 |

### Rechercher un code à partir d'un nom

```bash
python3 scripts/generer-territoire.py --recherche commune:Bauge
python3 scripts/generer-territoire.py --recherche epci:Bauge
python3 scripts/generer-territoire.py --recherche departement:Maine
python3 scripts/generer-territoire.py --recherche region:Pays
```

La commande affiche les codes correspondants, à réutiliser avec `--zone`.

### Exemples

```bash
# Une seule commune
python3 scripts/generer-territoire.py --zone commune:49018

# Deux communes (territoire discontinu)
python3 scripts/generer-territoire.py --zone commune:49018 --zone commune:49340

# Une communauté de communes entière
python3 scripts/generer-territoire.py --zone epci:244900015

# Un département entier
python3 scripts/generer-territoire.py --zone departement:49

# Une région entière
python3 scripts/generer-territoire.py --zone region:52

# Un pays entier (Natural Earth, contour simplifié)
python3 scripts/generer-territoire.py --zone pays:FR

# Combinaison libre de plusieurs zones de types différents
python3 scripts/generer-territoire.py \
    --zone commune:49018 \
    --zone epci:244900015 \
    --zone departement:44
```

## Comment l'application utilise ce fichier

- **Contrôle des envois** : un point situé dans *n'importe laquelle* des zones
  du fichier est accepté — les zones fonctionnent comme une union.
- **Affichage** : la limite du territoire est tracée sur les cartes publiques
  et la carte s'y ajuste automatiquement.
- **Configuration** : le fichier est déclaré dans `config/config.php`
  (`data_sources.territory.file`). Pour autoriser les envois **sans aucune
  limite territoriale**, supprimez l'entrée `territory` ou renseignez `file`
  avec une chaîne vide.
- Chaque zone générée est étiquetée dans le GeoJSON avec ses propriétés
  `zone_type` et `zone_code`, ce qui facilite les vérifications.

## Fichier produit

Le script écrit une `FeatureCollection` GeoJSON (WGS84) dans
`data/territoire.geojson`. Vous pouvez aussi **éditer ce fichier à la main** ou
avec un outil comme [QGIS](https://qgis.org) ou
[uMap](https://umap.openstreetmap.fr) : toute géométrie `Polygon` ou
`MultiPolygon` est acceptée.

## Script complémentaire

`scripts/generate_geojson.py` (plus ancien) génère à la fois
`territoire.geojson` (à partir de codes de communes) et
`communes-deleguees.geojson` (les secteurs). Pour un territoire multi-zones,
préferez `generer-territoire.py` puis générez les secteurs avec
`generate_geojson.py` si besoin.
