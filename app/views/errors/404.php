<?php
$pageTitle = 'Página Não Encontrada';
require_once APP_PATH . '/views/templates/header.php';
?>

<div class="auth-container">
    <div class="auth-card fade-in" style="text-align: center;">
        <div style="font-size: 6rem; margin-bottom: var(--space-6);">🔍</div>
        <h1 style="font-size: var(--text-4xl); color: var(--neutral-900); margin-bottom: var(--space-4);">
            404 - Página Não Encontrada
        </h1>
        <p style="color: var(--neutral-600); margin-bottom: var(--space-8);">
            A página que você está procurando não existe ou foi movida.
        </p>
        <a href="index.php?page=dashboard" class="btn btn-primary">
            ← Voltar ao Dashboard
        </a>
    </div>
</div>

<?php require_once APP_PATH . '/views/templates/footer.php'; ?>

