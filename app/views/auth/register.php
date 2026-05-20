<?php
$pageTitle = 'Novo Usuario';
require_once APP_PATH . '/views/templates/header.php';
?>

<div class="auth-container">
    <div class="auth-card fade-in">
        <div class="auth-header">
            <h1 class="auth-logo">Ferragens Souza</h1>
            <p class="auth-subtitle">Cadastro interno autorizado</p>
        </div>

        <form action="index.php?page=register&action=doRegister" method="POST" data-validate>
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

            <div class="form-group">
                <label for="name" class="form-label required">Nome completo</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    class="form-control"
                    placeholder="Seu nome completo"
                    required
                >
            </div>

            <div class="form-group">
                <label for="username" class="form-label required">Usuario</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    class="form-control"
                    placeholder="Ex.: FerragensSouza"
                    required
                    autocomplete="username"
                >
            </div>

            <div class="form-group">
                <label for="email" class="form-label required">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    placeholder="seu@email.com"
                    required
                >
            </div>

            <div class="form-group">
                <label for="role" class="form-label required">Perfil</label>
                <select id="role" name="role" class="form-control" required>
                    <?php foreach (USER_ROLES as $key => $label): ?>
                        <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="password" class="form-label required">Senha</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control"
                    placeholder="Crie uma senha"
                    required
                    data-strong-password
                >
                <small class="form-text">Minimo 8 caracteres, 1 maiuscula, 1 minuscula e 1 numero</small>
            </div>

            <div class="form-group">
                <label for="confirm_password" class="form-label required">Confirmar senha</label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    class="form-control"
                    placeholder="Repita a senha"
                    required
                    data-confirm-password="#password"
                >
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">
                Criar conta
            </button>
        </form>

        <div class="auth-footer">
            <p style="color: var(--neutral-600);">
                <a href="index.php?page=users" style="color: var(--primary-600); font-weight: 600;">
                    Voltar para usuarios
                </a>
            </p>
        </div>
    </div>
</div>

<?php require_once APP_PATH . '/views/templates/footer.php'; ?>
