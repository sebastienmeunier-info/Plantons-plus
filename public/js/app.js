(() => {
  'use strict';
  const config = window.PlantonsConfig;
  const form = document.querySelector('#proposal-form');
  const locationOutput = document.querySelector('#selected-location');
  const message = document.querySelector('#form-message');
  const toast = document.querySelector('#toast');
  const objectiveStatistics = document.querySelector('#objective-statistics');
  const map = L.map('map', { scrollWheelZoom: window.matchMedia('(min-width: 48rem)').matches }).setView(config.center, config.zoom);
  const defaultBaseLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
  window.PlantonsMapLayerControl?.add(map, defaultBaseLayer);
  let marker;
  let territory;
  let sectors;
  let toastTimer;
  let locationRequest = 0;
  let preparedPhotos = [];
  let photoPreparation = Promise.resolve();

  const setMessage = (text, kind = '') => { message.textContent = text; message.className = `form-message ${kind}`; };
  const updateObjectiveStatistics = () => {
    if (!objectiveStatistics) return;
    const proposed = Number(objectiveStatistics.dataset.proposed || 0) + 1;
    const validated = Number(objectiveStatistics.dataset.validated || 0);
    const planted = Number(objectiveStatistics.dataset.planted || 0);
    const noun = config.treeProposal ? 'arbres' : 'plantations';
    const format = new Intl.NumberFormat('fr-FR');
    objectiveStatistics.dataset.proposed = String(proposed);
    objectiveStatistics.textContent = `Objectif : ${format.format(config.targetCount)} ${noun} — ${format.format(proposed)} ${noun} proposés, ${format.format(validated)} ${noun} validés, ${format.format(planted)} arbres plantés.`;
  };
  const showToast = (text, kind = '') => { clearTimeout(toastTimer); toast.textContent = text; toast.className = `toast ${kind}`; toast.hidden = false; toastTimer = window.setTimeout(() => { toast.hidden = true; }, 5000); };
  const markerDetails = (status = 'a_valider') => {
    const square = config.markerShape === 'square';
    const triangle = config.markerShape === 'triangle';
    const shapeClass = square ? ' proposal-marker--square' : (triangle ? ' proposal-marker--triangle' : '');
    const icon = square || triangle ? '' : '●';
    if (['refusee', 'rejetee'].includes(status)) return { className: `proposal-marker--rejected${shapeClass}`, icon };
    if (status === 'validee') return { className: `proposal-marker--validated${shapeClass}`, icon };
    if (['arbre_plante', 'realisee'].includes(status)) return { className: `proposal-marker--planted${shapeClass}`, icon: square || triangle ? '' : '🌳' };
    if (status === 'remarquable') return { className: `proposal-marker--remarkable${shapeClass}`, icon: square || triangle ? '' : '★' };
    return { className: shapeClass.trim(), icon };
  };
  const markerWidth = Number(config.markerWidth || 1), markerHeight = Number(config.markerHeight || 1);
  const proposalIcon = (status) => { const details = markerDetails(status); return L.divIcon({ className: '', html: `<span class="proposal-marker ${details.className}" aria-hidden="true">${details.icon}</span>`, iconSize: [42 * markerWidth, 42 * markerHeight], iconAnchor: [21 * markerWidth, 21 * markerHeight] }); };
  const renderProposal = (feature) => L.geoJSON(feature, { pointToLayer: (item, latLng) => L.marker(latLng, { icon: proposalIcon(item.properties?.status) }), onEachFeature: (item, layer) => { const properties = item.properties || {}; const details = properties.conditioning ? [config.conditionings?.[properties.conditioning]?.label || properties.conditioning, properties.tree_size ? (config.treeSizes?.[properties.tree_size]?.label || properties.tree_size) : ''].filter(Boolean).join(' · ') : (properties.objectives || []).map((objective) => config.objectives[objective]?.label || objective).join(', '); layer.bindPopup(`<strong>${properties.species || 'Proposition'}</strong><br>${details || 'Information non renseignée'}`); } }).addTo(map);
  const sectorAt = (longitude, latitude) => sectors?.features?.find((feature) => window.turf && turf.booleanPointInPolygon(turf.point([longitude, latitude]), feature))?.properties?.nom || '';
  const shortAddress = (place) => {
    if (place?.properties) {
      const number = String(place.properties.housenumber || place.properties.house_number || '').trim();
      const street = String(place.properties.name || '').trim();
      const numberAndStreet = number && !street.startsWith(number) ? number + ' ' + street : street;
      return [numberAndStreet, place.properties.postcode, place.properties.city].filter(Boolean).join(', ');
    }
    const address = place.address || {};
    const street = [address.house_number, address.road || address.pedestrian || address.footway].filter(Boolean).join(' ');
    return street || address.amenity || address.building || address.hamlet || (place.display_name || '').split(',')[0] || 'adresse non trouvée';
  };
  const describeLocation = async (latitude, longitude, knownAddress = '') => {
    const request = ++locationRequest;
    let address = knownAddress;
    const sector = sectorAt(longitude, latitude);
    const sectorDisplay = config.sectorsUrl ? ` (${config.sectorType || 'secteur'} : ${sector || 'non identifié'})` : '';
    locationOutput.value = `${latitude.toFixed(6)}, ${longitude.toFixed(6)} — recherche de l’adresse…${sectorDisplay}`;
    if (!address) {
      try { const response = await fetch(`${config.geocodingReverseUrl}?lat=${latitude}&lon=${longitude}&limit=1`, { headers: { Accept: 'application/geo+json, application/json' } }); const payload = await response.json(); address = shortAddress(payload.features?.[0] || payload); } catch { address = ''; }
    }
    if (request !== locationRequest) return;
    document.querySelector('#selected-address').value = address;
    locationOutput.value = `${latitude.toFixed(6)}, ${longitude.toFixed(6)} — ${address || 'adresse non trouvée'}${sectorDisplay}`;
  };
  const setPosition = (latitude, longitude, address = '') => {
    const latLng = [latitude, longitude];
    if (territory && window.turf && !territory.features.some((feature) => turf.booleanPointInPolygon(turf.point([longitude, latitude]), feature))) {
      setMessage('Ce point est situé hors du territoire autorisé.', 'error');
      return;
    }
    marker ? marker.setLatLng(latLng) : (marker = L.marker(latLng, { icon: proposalIcon('a_valider') }).addTo(map));
    map.setView(latLng, Math.max(map.getZoom(), 16));
    document.querySelector('#latitude').value = latitude;
    document.querySelector('#longitude').value = longitude;
    describeLocation(latitude, longitude, address);
    setMessage('');
  };

  if (config.territoryUrl) fetch(config.territoryUrl).then((response) => response.ok ? response.json() : null).then((geojson) => {
    if (!geojson || !geojson.features?.length) return;
    territory = geojson;
    const layer = L.geoJSON(geojson, { style: { color: '#1f6b3b', weight: 2, fillOpacity: .08 } }).addTo(map);
    map.fitBounds(layer.getBounds(), { padding: [16, 16], maxZoom: config.zoom });
  }).catch(() => setMessage('La limite du territoire n’est pas disponible pour le moment.', 'error'));
  if (config.sectorsUrl) fetch(config.sectorsUrl).then((response) => response.ok ? response.json() : null).then((geojson) => { sectors = geojson; });
  fetch(config.proposalsUrl).then((response) => response.ok ? response.json() : null).then((geojson) => {
    if (!geojson?.features?.length) return;
    geojson.features.forEach(renderProposal);
  });

  map.on('click', (event) => setPosition(event.latlng.lat, event.latlng.lng));
  document.querySelector('#locate-me').addEventListener('click', () => {
    if (!navigator.geolocation) return setMessage('La géolocalisation n’est pas disponible sur cet appareil.', 'error');
    navigator.geolocation.getCurrentPosition((position) => setPosition(position.coords.latitude, position.coords.longitude), () => setMessage('Votre position n’a pas pu être obtenue.', 'error'), { enableHighAccuracy: true, timeout: 10000 });
  });
  document.querySelector('#address-search').addEventListener('submit', async (event) => {
    event.preventDefault(); const query = document.querySelector('#address').value.trim(); if (query.length < 3) return;
    const results = document.querySelector('#address-results'); results.textContent = 'Recherche en cours…';
    try { const response = await fetch(`${config.geocodingSearchUrl}?q=${encodeURIComponent(query)}&limit=5`, { headers: { Accept: 'application/geo+json, application/json' } }); const payload = await response.json(); const places = payload.features || payload; results.replaceChildren(...places.map((place) => { const button = document.createElement('button'), coordinates = place.geometry?.coordinates || [place.lon, place.lat]; button.type = 'button'; button.textContent = place.properties?.label || place.display_name; button.onclick = () => { setPosition(Number(coordinates[1]), Number(coordinates[0]), shortAddress(place)); results.textContent = ''; }; return button; })); } catch { results.textContent = 'La recherche d’adresse est indisponible.'; }
  });
  const canvasBlob = (canvas, quality) => new Promise((resolve) => canvas.toBlob(resolve, 'image/webp', quality));
  const compressPhoto = async (file) => {
    const source = await createImageBitmap(file);
    let scale = Math.min(1, 1600 / Math.max(source.width, source.height));
    for (let attempt = 0; attempt < 6; attempt++) {
      const canvas = document.createElement('canvas'); canvas.width = Math.max(1, Math.round(source.width * scale)); canvas.height = Math.max(1, Math.round(source.height * scale));
      canvas.getContext('2d').drawImage(source, 0, 0, canvas.width, canvas.height);
      const blob = await canvasBlob(canvas, Math.max(.45, .82 - attempt * .07));
      if (blob && blob.size <= 512000) { source.close(); return new File([blob], `${file.name.replace(/\.[^.]+$/, '')}.webp`, { type: 'image/webp' }); }
      scale *= .78;
    }
    source.close(); throw new Error('Une photo ne peut pas être réduite à 500 Ko.');
  };
  const photoInputs = [...document.querySelectorAll('.photo-input')];
  const preparePhotos = () => {
    const files = photoInputs.flatMap((input) => [...input.files]); const previews = document.querySelector('#photo-previews'); previews.textContent = '';
    if (files.length > config.maxPhotos) { photoInputs.forEach((input) => { input.value = ''; }); setMessage(`Vous pouvez sélectionner ${config.maxPhotos} photos maximum.`, 'error'); return; }
    photoPreparation = (async () => { try { setMessage('Optimisation des photos…'); preparedPhotos = await Promise.all(files.map(compressPhoto)); for (const file of preparedPhotos) { const image = document.createElement('img'); image.src = URL.createObjectURL(file); image.alt = `Aperçu de ${file.name}`; previews.append(image); } setMessage(''); } catch (error) { preparedPhotos = []; photoInputs.forEach((input) => { input.value = ''; }); previews.textContent = ''; setMessage(error.message, 'error'); } })();
  };
  photoInputs.forEach((input) => input.addEventListener('change', preparePhotos));
  form.querySelectorAll('input[name="objectives[]"]').forEach((input) => input.addEventListener('change', () => {
    const selected = form.querySelectorAll('input[name="objectives[]"]:checked');
    if (selected.length > config.maxObjectives) { input.checked = false; showToast(`Vous pouvez sélectionner ${config.maxObjectives} objectifs maximum.`, 'error'); }
  }));
  form.addEventListener('submit', async (event) => {
    event.preventDefault(); if (!form.latitude.value || !form.longitude.value) return setMessage('Choisissez l’emplacement de l’arbre sur la carte.', 'error'); await photoPreparation;
    const submit = form.querySelector('[type="submit"]'); submit.disabled = true; setMessage('Envoi de la proposition…');
    try { const formData = new FormData(form); formData.delete('photos[]'); preparedPhotos.forEach((photo) => formData.append('photos[]', photo, photo.name)); const response = await fetch(config.proposalUrl, { method: 'POST', body: formData }); const data = await response.json(); if (!response.ok) throw new Error(data.message || 'Envoi impossible.'); form.reset(); preparedPhotos = []; document.querySelector('#photo-previews').textContent = ''; marker?.remove(); marker = undefined; renderProposal(data.feature); updateObjectiveStatistics(); locationOutput.value = 'Choisissez un point sur la carte.'; setMessage(''); showToast(data.message); } catch (error) { setMessage(error.message, 'error'); } finally { submit.disabled = false; }
  });
})();
