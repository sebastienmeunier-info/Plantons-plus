<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0d2115">
    <title><?= htmlspecialchars($pageTitle ?? 'Accueil', ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($application['name'], ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($url('/public/css/app.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="outdoor-mode">
<?php require __DIR__ . '/partials/header.php'; ?>
<main class="page-layout">
  <section class="intro"><h1><?= htmlspecialchars($application['name'], ENT_QUOTES, 'UTF-8') ?></h1><p>Les plantations, dons et recensements validés par la collectivité.</p></section>
  <div class="overview-layout">
    <section class="overview-map-panel map-panel" aria-label="Carte des contributions validées">
      <div id="overview-map" class="overview-map" role="application" aria-label="Carte du territoire"></div>
    </section>
    <aside class="overview-description form-panel" aria-label="Présentation du projet">
      <?= $home['description_html'] ?>
    </aside>
  </div>
  <aside class="map-legend overview-legend" aria-label="Légende des contributions">
    <strong>Légende</strong>
    <span><i class="legend-marker legend-marker--validated" aria-hidden="true">●</i> Plantation validée</span>
    <span><i class="legend-marker legend-marker--validated legend-marker--square" aria-hidden="true"></i> Don d'arbre validé</span>
    <span><i class="legend-marker legend-marker--planted" aria-hidden="true">🌳</i> Arbre planté</span>
    <span><i class="legend-marker legend-marker--validated legend-marker--triangle" aria-hidden="true"></i> Arbre recensé validé</span>
    <span><i class="legend-marker legend-marker--remarkable legend-marker--triangle" aria-hidden="true"></i> Arbre remarquable</span>
  </aside>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const overviewConfig = <?= json_encode([
    'center' => $territory['center'],
    'zoom' => $map['default_zoom'],
    'minZoom' => $map['min_zoom'],
    'maxZoom' => $map['max_zoom'],
    'territoryUrl' => !empty($dataSources['territory']['file']) ? $routeUrl((string) ($dataSources['territory']['url'] ?? '')) : '',
    'sources' => [
        ['url' => $routeUrl((string) $dataSources['proposals']['url']), 'shape' => 'round', 'statuses' => ['validee', 'arbre_plante', 'realisee']],
        ['url' => $routeUrl((string) $dataSources['donations']['url']), 'shape' => 'square', 'statuses' => ['validee', 'arbre_plante', 'realisee']],
        ['url' => $routeUrl((string) $dataSources['trees']['url']), 'shape' => 'triangle', 'statuses' => ['validee', 'remarquable']],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const overviewMap = L.map('overview-map').setView(overviewConfig.center, overviewConfig.zoom);
overviewMap.setMinZoom(overviewConfig.minZoom).setMaxZoom(overviewConfig.maxZoom);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(overviewMap);
if (overviewConfig.territoryUrl) {
  fetch(overviewConfig.territoryUrl).then((response) => response.ok ? response.json() : null).then((geojson) => {
    if (!geojson || !geojson.features?.length) return;
    const layer = L.geoJSON(geojson, { style: { color: '#1f6b3b', weight: 2, fillOpacity: .08 } }).addTo(overviewMap);
    overviewMap.fitBounds(layer.getBounds(), { padding: [16, 16], maxZoom: overviewConfig.zoom });
  }).catch(() => {});
}
const overviewIcon = (shape, status) => {
  const square = shape === 'square', triangle = shape === 'triangle';
  const shapeClass = square ? ' proposal-marker--square' : (triangle ? ' proposal-marker--triangle' : '');
  let statusClass = '', icon = square || triangle ? '' : '●';
  if (['arbre_plante', 'realisee'].includes(status)) { statusClass = ' proposal-marker--planted'; icon = square || triangle ? '' : '🌳'; }
  else if (status === 'remarquable') { statusClass = ' proposal-marker--remarkable'; icon = square || triangle ? '' : '★'; }
  else if (status === 'validee') { statusClass = ' proposal-marker--validated'; }
  return L.divIcon({ className: '', html: `<span class="proposal-marker${statusClass}${shapeClass}" aria-hidden="true">${icon}</span>`, iconSize: [42, 42], iconAnchor: [21, 21] });
};
overviewConfig.sources.forEach((source) => {
  fetch(source.url).then((response) => response.ok ? response.json() : null).then((geojson) => {
    if (!geojson || !geojson.features?.length) return;
    geojson.features.forEach((feature) => {
      const status = feature.properties?.status;
      if (!source.statuses.includes(status)) return;
      const coordinates = feature.geometry?.coordinates;
      if (!Array.isArray(coordinates) || coordinates.length < 2) return;
      const properties = feature.properties || {};
      L.marker([coordinates[1], coordinates[0]], { icon: overviewIcon(source.shape, status) }).addTo(overviewMap)
        .bindPopup(`<strong>${properties.species || 'Arbre'}</strong>`);
    });
  }).catch(() => {});
});
</script>
</body>
</html>
