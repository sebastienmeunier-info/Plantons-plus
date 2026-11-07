<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="<?= htmlspecialchars($url('/public/css/app.css'), ENT_QUOTES, 'UTF-8') ?>"><?php require __DIR__ . '/partials/favicon.php'; ?>
  <title>À propos · <?= htmlspecialchars($application['name'], ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body class="outdoor-mode">
<?php require __DIR__ . '/partials/header.php'; ?>
<main class="auth-page about-page"><?php $collectivityLogoVariant = 'corner'; require __DIR__ . '/partials/collectivity-logo.php'; ?>
  <h1>À propos</h1>
  <section>
    <h2>Un outil pour agir ensemble</h2>
    <p><?= htmlspecialchars($application['name'], ENT_QUOTES, 'UTF-8') ?> facilite la participation citoyenne aux plantations et aux dons d’arbres. Chacun peut localiser une idée, expliquer son objectif et suivre son évolution ; la collectivité peut ensuite l’instruire et la valoriser sur la carte.</p>
  </section>
  <section>
    <h2>Adaptable à chaque territoire</h2>
    <p>L’application ne dépend pas d’un territoire particulier. Le nom du projet, le logo, les limites géographiques, les communes associées, les essences et les objectifs de plantation sont regroupés dans la configuration. Une commune, une intercommunalité ou une association peut ainsi l’adapter à son propre contexte.</p>
  </section>
  <section>
    <h2>Simple à déployer</h2>
    <p>Plantons fonctionne avec PHP et des fichiers JSON/GeoJSON : aucune base de données SQL ni installation complexe ne sont nécessaires. Les fichiers peuvent être transférés par FTP sur un hébergement compatible PHP, puis configurés depuis un unique fichier <code>config/config.php</code>.</p>
  </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
