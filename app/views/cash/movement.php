<?php
$pageTitle = 'Movimento de Caixa';
require_once APP_PATH . '/views/templates/header.php';
?>
<div class="app-container">
    <?php require_once APP_PATH . '/views/templates/navigation.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left"><h1 class="topbar-title">Movimento de Caixa</h1></div>
            <div class="topbar-right"><a href="index.php?page=cash" class="btn btn-secondary">Voltar</a></div>
        </header>
        <div class="content-area">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Sangria ou suprimento</h3></div>
                <div class="card-body">
                    <form action="index.php?page=cash&action=saveMovement" method="POST" data-validate>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <div class="form-grid-2col">
                            <div class="form-group">
                                <label for="type" class="form-label required">Tipo</label>
                                <select id="type" name="type" class="form-control" required>
                                    <option value="supply">Suprimento</option>
                                    <option value="withdrawal">Sangria</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="amount" class="form-label required">Valor</label>
                                <input type="number" id="amount" name="amount" class="form-control" min="0.01" step="0.01" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="reason" class="form-label required">Motivo</label>
                            <input type="text" id="reason" name="reason" class="form-control" required>
                        </div>
                        <div class="form-section-title">Autorizacao para sangria</div>
                        <div class="form-grid-2col">
                            <div class="form-group">
                                <label for="approval_email" class="form-label">Email do gerente</label>
                                <input type="email" id="approval_email" name="approval_email" class="form-control" autocomplete="username">
                            </div>
                            <div class="form-group">
                                <label for="approval_password" class="form-label">Senha do gerente</label>
                                <input type="password" id="approval_password" name="approval_password" class="form-control" autocomplete="current-password">
                            </div>
                        </div>
                        <div class="form-actions">
                            <a href="index.php?page=cash" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-success">Registrar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once APP_PATH . '/views/templates/footer.php'; ?>
