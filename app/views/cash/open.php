<?php
$pageTitle = 'Abrir Caixa';
$cashBackUrl = hasPermission('manager') ? 'index.php?page=cash' : 'index.php?page=pos';
require_once APP_PATH . '/views/templates/header.php';
?>
<div class="app-container">
    <?php require_once APP_PATH . '/views/templates/navigation.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left"><h1 class="topbar-title">Abrir Caixa</h1></div>
            <div class="topbar-right"><a href="<?php echo $cashBackUrl; ?>" class="btn btn-secondary">Voltar</a></div>
        </header>
        <div class="content-area">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Saldo inicial</h3></div>
                <div class="card-body">
                    <form action="index.php?page=cash&action=saveOpen" method="POST" data-validate>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <div class="form-group">
                            <label for="initial_balance" class="form-label required">Troco inicial em dinheiro</label>
                            <input type="number" id="initial_balance" name="initial_balance" class="form-control" min="0" step="0.01" value="0.00" required>
                        </div>
                        <div class="form-actions">
                            <a href="<?php echo $cashBackUrl; ?>" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-success">Abrir Caixa</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once APP_PATH . '/views/templates/footer.php'; ?>
