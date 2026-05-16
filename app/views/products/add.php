<?php
$pageTitle = 'Novo Produto';
$isEdit = false;
require_once APP_PATH . '/views/templates/header.php';
?>

<div class="app-container">
    <?php require_once APP_PATH . '/views/templates/navigation.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <h1 class="topbar-title">Novo Produto</h1>
            </div>
            <div class="topbar-right">
                <a href="index.php?page=products" class="btn btn-secondary">Voltar</a>
            </div>
        </header>

        <div class="content-area">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Cadastro de produto</h3>
                </div>
                <div class="card-body">
                    <form action="index.php?page=products&action=save" method="POST" data-validate>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <?php require APP_PATH . '/views/products/form-fields.php'; ?>

                        <div class="form-actions">
                            <a href="index.php?page=products" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-success">Salvar Produto</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once APP_PATH . '/views/templates/footer.php'; ?>
