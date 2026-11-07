<!doctype html>
<html lang="fr">
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="<?= htmlspecialchars($url('/public/css/app.css'), ENT_QUOTES, 'UTF-8') ?>"><?php require __DIR__ . '/../partials/favicon.php'; ?><title>Utilisateurs · <?= htmlspecialchars($application['name']) ?></title>
<body class="outdoor-mode">
<?php require __DIR__ . '/../partials/header.php'; ?>
<main class="auth-page admin-page">
  <h1>Utilisateurs</h1><p><a href="<?= htmlspecialchars($routeUrl('/admin/propositions'), ENT_QUOTES, 'UTF-8') ?>">Gérer les propositions</a></p>
  <table class="users-table"><thead><tr><th>Nom</th><th>E-mail</th><th>Rôle</th><th>Actions</th></tr></thead><tbody>
  <?php foreach ($users as $listedUser): ?><tr>
    <td><?= htmlspecialchars($listedUser['first_name'] . ' ' . $listedUser['last_name']) ?></td><td><?= htmlspecialchars($listedUser['email']) ?></td><td><?= htmlspecialchars(str_replace('_', ' ', $listedUser['role'])) ?></td><td>
    <?php if ($listedUser['role'] === 'super_administrateur'): ?>Protégé
    <?php elseif ($user['role'] === 'administrateur' && $listedUser['role'] === 'contributeur'): ?>
      <form method="post" action="<?= htmlspecialchars($routeUrl('/admin/utilisateurs/role'), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="user_id" value="<?= htmlspecialchars($listedUser['id']) ?>"><input type="hidden" name="role" value="administrateur"><button>Passer administrateur</button></form>
    <?php elseif ($user['role'] === 'super_administrateur' && $listedUser['role'] === 'administrateur'): ?>
      <div class="inline-actions"><form method="post" action="<?= htmlspecialchars($routeUrl('/admin/utilisateurs/role'), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="user_id" value="<?= htmlspecialchars($listedUser['id']) ?>"><input type="hidden" name="role" value="contributeur"><button>Rétrograder</button></form><form method="post" action="<?= htmlspecialchars($routeUrl('/admin/utilisateurs/role'), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="user_id" value="<?= htmlspecialchars($listedUser['id']) ?>"><input type="hidden" name="role" value="super_administrateur"><button>Passer super-admin</button></form></div>
    <?php endif; ?></td>
  </tr><?php endforeach; ?></tbody></table>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?></body></html>
