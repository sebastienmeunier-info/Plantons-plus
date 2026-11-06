<?php
$collectivityLogoUrl = trim((string) ($application['collectivity_logo_url'] ?? ''));
?>
<?php if ($collectivityLogoUrl !== ''): ?>
<img class="collectivity-logo<?= isset($collectivityLogoVariant) ? ' collectivity-logo--' . htmlspecialchars($collectivityLogoVariant, ENT_QUOTES, 'UTF-8') : '' ?>" src="<?= htmlspecialchars($collectivityLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Logo de la collectivité">
<?php endif; ?>
