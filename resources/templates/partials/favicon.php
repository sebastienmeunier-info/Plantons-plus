<?php
$faviconUrl = trim((string) ($application['favicon_url'] ?? ''));
?>
<?php if ($faviconUrl !== ''): ?>
<link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars($faviconUrl, ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
