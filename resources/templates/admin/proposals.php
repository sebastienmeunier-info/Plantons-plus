<!doctype html>
<html lang="fr">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="<?= htmlspecialchars($url('/public/css/app.css'), ENT_QUOTES, 'UTF-8') ?>"><?php require __DIR__ . '/../partials/favicon.php'; ?>
<title>Propositions · <?= htmlspecialchars($application['name']) ?></title>
<body class="outdoor-mode">
<?php require __DIR__ . '/../partials/header.php'; ?>
<main class="auth-page admin-page">
  <h1>Propositions</h1>
  <p>Un administrateur peut mettre à jour le statut et déplacer une localisation à l’intérieur du territoire.</p>
  <?php if ($features === []): ?><p>Aucune proposition pour le moment.</p><?php endif; ?>
  <div class="proposal-admin-list">
  <?php foreach ($features as $feature): $properties = $feature['properties'] ?? []; $coordinates = $feature['geometry']['coordinates'] ?? [null, null]; ?>
    <form class="proposal-admin-card" method="post" action="<?= htmlspecialchars($routeUrl('/admin/propositions/modifier'), ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($properties['id'] ?? '')) ?>">
      <h2><?= htmlspecialchars((string) ($properties['species'] ?? 'Plantation')) ?></h2>
      <p><?= htmlspecialchars(implode(', ', $properties['objectives'] ?? [])) ?></p>
      <label>Statut<select name="status"><?php foreach ($statuses as $value => $label): ?><option value="<?= htmlspecialchars($value) ?>" <?= ($properties['status'] ?? 'a_valider') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select></label>
      <div class="coordinate-fields"><label>Longitude<input type="number" name="longitude" step="any" value="<?= htmlspecialchars((string) $coordinates[0]) ?>" required></label><label>Latitude<input type="number" name="latitude" step="any" value="<?= htmlspecialchars((string) $coordinates[1]) ?>" required></label></div>
      <button>Enregistrer</button>
    </form>
  <?php endforeach; ?>
  </div>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
