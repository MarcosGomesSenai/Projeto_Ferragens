<?php
$pageTitle = 'Editar Fornecedor';
$isEdit = true;
require_once APP_PATH . '/views/templates/header.php';
?>

<div class="app-container">
    <?php require_once APP_PATH . '/views/templates/navigation.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left"><h1 class="topbar-title">Editar Fornecedor</h1></div>
            <div class="topbar-right"><a href="index.php?page=suppliers" class="btn btn-secondary">Voltar</a></div>
        </header>

        <div class="content-area">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><?php echo htmlspecialchars($supplier['name']); ?></h3></div>
                <div class="card-body">
                    <form action="index.php?page=suppliers&action=update" method="POST" data-validate>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="id" value="<?php echo (int) $supplier['id']; ?>">
                        <?php require APP_PATH . '/views/suppliers/form-fields.php'; ?>
                        <div class="form-actions">
                            <a href="index.php?page=suppliers" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-success">Atualizar Fornecedor</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once APP_PATH . '/views/templates/footer.php'; ?>
