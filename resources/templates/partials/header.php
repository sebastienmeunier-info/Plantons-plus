<?php
$headerText = trim((string) ($application['header_text'] ?? ''));
$defaultHeaderText = "Plantons + d’arbres sur notre territoire";
$headerLabel = $headerText !== '' ? $headerText : $defaultHeaderText;
?>
<header class="site-header">
  <a class="project-brand" href="<?= htmlspecialchars($routeUrl('/'), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($headerLabel, ENT_QUOTES, 'UTF-8') ?>"><img class="project-logo" src="<?= htmlspecialchars((string) ($application['logo_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" alt=""><?php if ($headerText === ''): ?><strong class="project-title">Plantons <span class="project-title-plus">+</span> d’arbres sur notre territoire</strong><?php else: ?><strong class="project-title"><?= htmlspecialchars($headerText, ENT_QUOTES, 'UTF-8') ?></strong><?php endif; ?></a>
  <button class="menu-toggle" type="button" aria-label="Ouvrir le menu" aria-controls="site-navigation" aria-expanded="false"><span></span><span></span><span></span></button>
  <nav id="site-navigation" class="site-navigation" aria-label="Navigation principale">
    <?php if ($user ?? null): ?>
      <span class="user-menu">Bonjour <?= htmlspecialchars($user['first_name'], ENT_QUOTES, 'UTF-8') ?></span>
      <a class="menu-button<?= ($isOverview ?? false) ? ' menu-current' : '' ?>" href="<?= htmlspecialchars($routeUrl('/'), ENT_QUOTES, 'UTF-8') ?>">Accueil</a>
      <a class="menu-button menu-proposal<?= (!($treeProposal ?? false) && !($isCensus ?? false) && !($isOverview ?? false)) ? ' menu-current' : '' ?>" href="<?= htmlspecialchars($routeUrl('/envie-d-un-arbre'), ENT_QUOTES, 'UTF-8') ?>">Envie d'un arbre</a>
      <a class="menu-button menu-proposal<?= ($treeProposal ?? false) ? ' menu-current' : '' ?>" href="<?= htmlspecialchars($routeUrl('/proposer-un-arbre'), ENT_QUOTES, 'UTF-8') ?>">Donner un arbre</a>
      <a class="menu-button menu-proposal<?= ($isCensus ?? false) ? ' menu-current' : '' ?>" href="<?= htmlspecialchars($routeUrl('/recenser-un-arbre'), ENT_QUOTES, 'UTF-8') ?>">Recenser un arbre</a>
      <a class="menu-button" href="<?= htmlspecialchars($routeUrl('/a-propos'), ENT_QUOTES, 'UTF-8') ?>">À propos</a>
      <a class="menu-button menu-admin" href="<?= htmlspecialchars($routeUrl('/mon-compte'), ENT_QUOTES, 'UTF-8') ?>">Espace Admin</a>
      <a class="menu-button" href="<?= htmlspecialchars($routeUrl('/donnees-personnelles'), ENT_QUOTES, 'UTF-8') ?>">Infos RGPD</a>
      <form class="logout-form" method="post" action="<?= htmlspecialchars($routeUrl('/deconnexion'), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>"><button class="menu-button menu-logout">Déconnexion</button></form>
    <?php else: ?>
      <a class="menu-button<?= ($isOverview ?? false) ? ' menu-current' : '' ?>" href="<?= htmlspecialchars($routeUrl('/'), ENT_QUOTES, 'UTF-8') ?>">Accueil</a>
      <a class="menu-button menu-proposal<?= (!($treeProposal ?? false) && !($isCensus ?? false) && !($isOverview ?? false)) ? ' menu-current' : '' ?>" href="<?= htmlspecialchars($routeUrl('/envie-d-un-arbre'), ENT_QUOTES, 'UTF-8') ?>">Envie d'un arbre</a>
      <a class="menu-button menu-proposal<?= ($treeProposal ?? false) ? ' menu-current' : '' ?>" href="<?= htmlspecialchars($routeUrl('/proposer-un-arbre'), ENT_QUOTES, 'UTF-8') ?>">Donner un arbre</a>
      <a class="menu-button menu-proposal<?= ($isCensus ?? false) ? ' menu-current' : '' ?>" href="<?= htmlspecialchars($routeUrl('/recenser-un-arbre'), ENT_QUOTES, 'UTF-8') ?>">Recenser un arbre</a>
      <a class="menu-button" href="<?= htmlspecialchars($routeUrl('/a-propos'), ENT_QUOTES, 'UTF-8') ?>">À propos</a>
      <a class="menu-button" href="<?= htmlspecialchars($routeUrl('/donnees-personnelles'), ENT_QUOTES, 'UTF-8') ?>">Infos RGPD</a><a class="menu-button" href="<?= htmlspecialchars($routeUrl('/connexion'), ENT_QUOTES, 'UTF-8') ?>">Connexion</a><a class="menu-button" href="<?= htmlspecialchars($routeUrl('/inscription'), ENT_QUOTES, 'UTF-8') ?>">Inscription</a>
    <?php endif; ?>
  </nav>
</header>
<script>document.addEventListener('DOMContentLoaded',()=>{const toggle=document.querySelector('.menu-toggle'),navigation=document.querySelector('#site-navigation');if(!toggle||!navigation)return;const close=()=>{navigation.classList.remove('is-open');toggle.setAttribute('aria-expanded','false');toggle.setAttribute('aria-label','Ouvrir le menu');};toggle.addEventListener('click',()=>{const open=!navigation.classList.contains('is-open');navigation.classList.toggle('is-open',open);toggle.setAttribute('aria-expanded',String(open));toggle.setAttribute('aria-label',open?'Fermer le menu':'Ouvrir le menu');});navigation.addEventListener('click',event=>{if(event.target.closest('a'))close();});document.addEventListener('keydown',event=>{if(event.key==='Escape')close();});});</script>
