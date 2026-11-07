<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="<?= htmlspecialchars($url('/public/css/app.css'), ENT_QUOTES, 'UTF-8') ?>"><?php require __DIR__ . '/partials/favicon.php'; ?><title>Données personnelles · <?= htmlspecialchars($application['name'], ENT_QUOTES, 'UTF-8') ?></title></head>
<body class="outdoor-mode"><?php require __DIR__ . '/partials/header.php'; ?>
<main class="auth-page privacy-page"><?php $collectivityLogoVariant = 'corner'; require __DIR__ . '/partials/collectivity-logo.php'; ?>
  <h1>Données personnelles</h1>
  <p><strong>Responsable du traitement :</strong> <?= htmlspecialchars($privacy['controller_name'], ENT_QUOTES, 'UTF-8') ?>.</p>
  <p><strong>Contact :</strong> <a href="mailto:<?= htmlspecialchars($privacy['contact_email'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($privacy['contact_email'], ENT_QUOTES, 'UTF-8') ?></a><?php if ($privacy['dpo_email'] !== ''): ?> · <strong>DPO :</strong> <a href="mailto:<?= htmlspecialchars($privacy['dpo_email'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($privacy['dpo_email'], ENT_QUOTES, 'UTF-8') ?></a><?php endif; ?>.</p>
  <h2>Pourquoi ces données sont utilisées</h2>
  <p>Les données servent à gérer les propositions, leur instruction, le suivi des plantations, les comptes contributeurs et, si vous l’avez demandé, les notifications par e-mail. La base légale est : <?= htmlspecialchars($privacy['legal_basis'], ENT_QUOTES, 'UTF-8') ?>. Le consentement est demandé séparément pour les notifications facultatives.</p>
  <h2>Données et destinataires</h2>
  <p>Nous pouvons traiter vos coordonnées, votre e-mail, vos coordonnées GPS, votre commentaire et vos photos. Elles sont accessibles uniquement aux personnes habilitées de la collectivité. La carte publique ne diffuse ni vos coordonnées, ni votre commentaire, ni vos photos ; les positions y sont arrondies. L’hébergeur, le prestataire SMTP et les services cartographiques sollicités depuis votre navigateur interviennent comme prestataires techniques.</p>
  <h2>Durées de conservation</h2>
  <p>Compte : <?= htmlspecialchars($privacy['account_retention'], ENT_QUOTES, 'UTF-8') ?>. Proposition, photographies et éléments associés : <?= htmlspecialchars($privacy['proposal_retention'], ENT_QUOTES, 'UTF-8') ?>.</p>
  <h2>Vos droits</h2>
  <p>Vous pouvez demander l’accès, la rectification, l’effacement, la limitation ou l’opposition au traitement de vos données. Adressez votre demande à <a href="mailto:<?= htmlspecialchars($privacy['contact_email'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($privacy['contact_email'], ENT_QUOTES, 'UTF-8') ?></a>. Vous pouvez également saisir la <a href="https://www.cnil.fr/" target="_blank" rel="noopener noreferrer">CNIL</a>.</p>
  <?php if ($user): ?><p><a class="menu-button" href="<?= htmlspecialchars($routeUrl('/mon-compte/donnees'), ENT_QUOTES, 'UTF-8') ?>">Télécharger mes données</a></p><?php endif; ?>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?></body></html>
