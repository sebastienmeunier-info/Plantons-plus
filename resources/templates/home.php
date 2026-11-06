<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0d2115">
    <title><?= htmlspecialchars($application['name'], ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($url('/public/css/app.css'), ENT_QUOTES, 'UTF-8') ?>">
    <style>:root { --marker-scale-width: <?= (float) $map['marker_width_coefficient'] ?>; --marker-scale-height: <?= (float) $map['marker_height_coefficient'] ?>; }</style>
</head>
<body class="outdoor-mode">
<?php require __DIR__ . '/partials/header.php'; ?>
<main class="page-layout">
    <section class="intro"><h1><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1><?php if ($isCensus): ?><p>Aidez la collectivité à connaître son patrimoine arboré : localisez un arbre existant et précisez son essence. Chaque recensement sera examiné avant publication.</p><?php elseif ($treeProposal): ?><p>Offrez un arbre à la collectivité, en pot ou en pleine terre : votre don contribuera au verdissement du territoire. Chaque don sera examiné avant plantation.</p><?php else: ?><p id="objective-statistics" data-proposed="<?= (int) $statistics['proposed'] ?>" data-validated="<?= (int) $statistics['validated'] ?>" data-planted="<?= (int) $statistics['planted'] ?>">Objectif : <?= number_format((int) $planting['target_count'], 0, ',', ' ') ?> <?= $treeProposal ? 'arbres' : 'plantations' ?> — <?= (int) $statistics['proposed'] ?> <?= $treeProposal ? 'arbres proposés' : 'plantations proposées' ?>, <?= (int) $statistics['validated'] ?> <?= $treeProposal ? 'arbres validés' : 'plantations validées' ?>, <?= (int) $statistics['planted'] ?> arbres plantés.</p><?php endif; ?></section>
    <section class="map-panel" aria-label="Choix de l'emplacement">
        <form id="address-search" class="address-search"><label for="address">Rechercher une adresse</label><div><input id="address" type="search" autocomplete="street-address" placeholder="Rue, lieu-dit, commune"><button type="submit">Rechercher</button></div></form>
        <div id="address-results" class="address-results" aria-live="polite"></div>
        <div id="map" role="application" aria-label="Carte du territoire"></div>
        <aside class="map-legend" aria-label="Légende des propositions"><?php if ($isCensus): ?><span><i class="legend-marker legend-marker--proposed legend-marker--triangle" aria-hidden="true"></i> Arbre recensé</span><span><i class="legend-marker legend-marker--rejected legend-marker--triangle" aria-hidden="true"></i> Arbre refusé</span><span><i class="legend-marker legend-marker--validated legend-marker--triangle" aria-hidden="true"></i> Arbre validé</span><span><i class="legend-marker legend-marker--remarkable legend-marker--triangle" aria-hidden="true"></i> Arbre remarquable</span><?php elseif ($treeProposal): ?><span><i class="legend-marker legend-marker--proposed legend-marker--square" aria-hidden="true"></i> Arbre proposé</span><span><i class="legend-marker legend-marker--rejected legend-marker--square" aria-hidden="true"></i> Arbre refusé</span><span><i class="legend-marker legend-marker--validated legend-marker--square" aria-hidden="true"></i> Arbre validé</span><span><i class="legend-marker legend-marker--planted legend-marker--square" aria-hidden="true"></i> Arbre planté</span><?php else: ?><span><i class="legend-marker legend-marker--proposed">●</i> Plantation proposée</span><span><i class="legend-marker legend-marker--rejected">●</i> Plantation refusée</span><span><i class="legend-marker legend-marker--validated">●</i> Plantation validée</span><span><i class="legend-marker legend-marker--planted">🌳</i> Arbre planté</span><?php endif; ?></aside>
        <div class="map-actions"><button id="locate-me" type="button">Utiliser ma position</button><output id="selected-location">Choisissez un point sur la carte.</output></div>
    </section>
    <section class="form-panel">
        <form id="proposal-form" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="latitude" id="latitude"><input type="hidden" name="longitude" id="longitude"><input type="hidden" name="address" id="selected-address">
            <label>Votre nom <small>facultatif</small><input name="author" maxlength="80" autocomplete="name" value="<?= htmlspecialchars($user ? $user['first_name'] . ' ' . $user['last_name'] : '', ENT_QUOTES, 'UTF-8') ?>"></label>
            <label>Votre adresse e-mail <small>facultative — pour recevoir les changements de statut</small><input name="email" type="email" maxlength="<?= (int) $security['max_email_length'] ?>" autocomplete="email" inputmode="email" placeholder="nom@exemple.fr" value="<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label>
            <label>Essence souhaitée<select name="species" required><option value="">Choisir une essence</option><?php foreach ($planting['allowed_species'] as $species): ?><option value="<?= htmlspecialchars($species, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($species, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label>
            <label class="privacy-consent"><input name="notification_consent" type="checkbox" value="1"<?= !empty($user['notification_consent_at']) ? ' checked' : '' ?>> J’accepte de recevoir par e-mail les notifications liées à cette proposition.</label>
            <?php if ($treeProposal): ?>
                <fieldset><legend>Conditionnement de l’arbre</legend><div class="objective-grid"><?php foreach ($planting['tree_conditioning'] as $key => $conditioning): ?><label class="objective"><input type="radio" name="conditioning" value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" required><span class="objective-tile"><span class="objective-icon" aria-hidden="true"><?= htmlspecialchars($conditioning['icon'], ENT_QUOTES, 'UTF-8') ?></span><span><?= htmlspecialchars($conditioning['label'], ENT_QUOTES, 'UTF-8') ?></span></span></label><?php endforeach; ?></div></fieldset>
                <fieldset><legend>Taille de l’arbre</legend><div class="objective-grid"><?php foreach ($planting['tree_sizes'] as $key => $size): ?><label class="objective"><input type="radio" name="tree_size" value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" required><span class="objective-tile"><span class="objective-icon" aria-hidden="true"><?= htmlspecialchars($size['icon'], ENT_QUOTES, 'UTF-8') ?></span><span><?= htmlspecialchars($size['label'], ENT_QUOTES, 'UTF-8') ?></span></span></label><?php endforeach; ?></div></fieldset>
            <?php elseif (!$isCensus): ?>
                <fieldset><legend>Objectifs de plantation <small>3 maximum</small></legend><div class="objective-grid"><?php foreach ($planting['objectives'] as $key => $objective): ?><label class="objective"><input type="checkbox" name="objectives[]" value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"><span class="objective-tile"><span class="objective-icon" aria-hidden="true"><?= htmlspecialchars($objective['icon'], ENT_QUOTES, 'UTF-8') ?></span><span><?= htmlspecialchars($objective['label'], ENT_QUOTES, 'UTF-8') ?></span></span></label><?php endforeach; ?></div></fieldset>
            <?php endif; ?>
            <label>Commentaire <small>facultatif</small><textarea name="comment" maxlength="1000" rows="4" placeholder="<?= $isCensus ? 'Précisez l’état de l’arbre, sa taille approximative ou toute remarque utile.' : ($treeProposal ? 'Précisez l’état général de l’arbre.' : 'Précisez votre idée de plantation.') ?>"></textarea></label>
            <fieldset class="photo-fieldset"><legend>Photos <small>jusqu'à <?= (int) $security['max_photos_per_proposal'] ?></small></legend><div class="photo-inputs"><?php for ($photoIndex = 1; $photoIndex <= (int) $security['max_photos_per_proposal']; $photoIndex++): ?><label>Photo <?= $photoIndex ?><input class="photo-input" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp"></label><?php endfor; ?></div></fieldset>
            <div id="photo-previews" class="photo-previews" aria-live="polite"></div>
            <p id="form-message" class="form-message" aria-live="polite"></p>
            <button class="submit-button" type="submit">Envoyer ma proposition</button>
        </form>
    </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
<div id="toast" class="toast" role="status" aria-live="polite" hidden></div>
<script>window.PlantonsConfig = <?= json_encode(['center' => $territory['center'], 'zoom' => $map['default_zoom'], 'territoryUrl' => !empty($dataSources['territory']['file']) ? $routeUrl((string) ($dataSources['territory']['url'] ?? '')) : '', 'sectorsUrl' => !empty($dataSources['sectors']['file']) ? $routeUrl((string) ($dataSources['sectors']['url'] ?? '')) : '', 'sectorType' => $territory['type_secteur'], 'proposalsUrl' => $routeUrl($activeDataSource['url']), 'proposalUrl' => $submissionUrl, 'geocodingSearchUrl' => $geocoding['search_url'], 'geocodingReverseUrl' => $geocoding['reverse_url'], 'markerShape' => $treeProposal ? 'square' : ($isCensus ? 'triangle' : 'round'), 'treeProposal' => $treeProposal, 'isCensus' => $isCensus, 'targetCount' => (int) $planting['target_count'], 'maxPhotos' => $security['max_photos_per_proposal'], 'maxObjectives' => $planting['max_objectives_per_proposal'], 'markerWidth' => (float) $map['marker_width_coefficient'], 'markerHeight' => (float) $map['marker_height_coefficient'], 'objectives' => $planting['objectives'], 'conditionings' => $planting['tree_conditioning'], 'treeSizes' => $planting['tree_sizes']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@turf/turf@7/turf.min.js"></script>
<script src="<?= htmlspecialchars($url('/public/js/app.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
</body>
</html>
