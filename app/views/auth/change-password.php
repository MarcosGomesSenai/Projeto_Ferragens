<?php
$pageTitle = 'Alterar Senha';
require_once APP_PATH . '/views/templates/header.php';
?>

<div class="auth-container">
    <div class="auth-card fade-in">
        <div class="auth-header">
            <h1 class="auth-logo">Ferragens Souza</h1>
            <p class="auth-subtitle">Atualize sua senha para continuar</p>
        </div>

        <form action="index.php?page=password&action=savePassword" method="POST" data-validate>
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

            <div class="form-group">
                <label for="current_password" class="form-label required">Senha atual</label>
                <input type="password" id="current_password" name="current_password" class="form-control" autocomplete="current-password" required>
            </div>

            <div class="form-group">
                <label for="password" class="form-label required">Nova senha</label>
                <input type="password" id="password" name="password" class="form-control" autocomplete="new-password" required data-strong-password>
                <small class="form-text">Minimo 8 caracteres, 1 maiuscula, 1 minuscula e 1 numero</small>
            </div>

            <div class="form-group">
                <label for="confirm_password" class="form-label required">Confirmar nova senha</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" autocomplete="new-password" required data-confirm-password="#password">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">Salvar senha</button>
        </form>
    </div>
</div>

<?php require_once APP_PATH . '/views/templates/footer.php'; ?>
