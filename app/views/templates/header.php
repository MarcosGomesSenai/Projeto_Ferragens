<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars(APP_DESCRIPTION); ?>">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : ''; ?><?php echo htmlspecialchars(APP_NAME); ?></title>
    <link rel="stylesheet" href="public/css/style.css">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='18' fill='%23f59e0b'/%3E%3Cpath d='M24 60h52v12H24zM32 30h36v12H32zM40 42h20v18H40z' fill='%23262626'/%3E%3C/svg%3E">
    <script nonce="<?php echo htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '', ENT_QUOTES, 'UTF-8'); ?>" src="public/js/app.js" defer></script>
</head>
<body>
<?php $flashMessage = getFlashMessage(); ?>
<?php if ($flashMessage): ?>
    <?php
    $allowedAlertTypes = ['success', 'error', 'warning', 'info'];
    $alertType = in_array($flashMessage['type'], $allowedAlertTypes, true) ? $flashMessage['type'] : 'info';
    $safeMessage = nl2br(htmlspecialchars($flashMessage['message'], ENT_QUOTES, 'UTF-8'));
    ?>
    <div class="alert alert-<?php echo $alertType; ?> fade-in flash-toast" data-auto-close="5000">
        <?php echo $safeMessage; ?>
    </div>
<?php endif; ?>
