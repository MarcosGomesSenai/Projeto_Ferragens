<?php
$pageTitle = 'Categorias';
require_once APP_PATH . '/views/templates/header.php';
$editCategory = $editCategory ?? null;
$formAction = $editCategory ? 'update' : 'save';
?>

<div class="app-container">
    <?php require_once APP_PATH . '/views/templates/navigation.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left"><h1 class="topbar-title">Categorias</h1></div>
        </header>

        <div class="content-area">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><?php echo $editCategory ? 'Editar Categoria' : 'Nova Categoria'; ?></h3></div>
                <div class="card-body">
                    <form action="index.php?page=categories&action=<?php echo $formAction; ?>" method="POST" data-validate>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <?php if ($editCategory): ?>
                            <input type="hidden" name="id" value="<?php echo (int) $editCategory['id']; ?>">
                        <?php endif; ?>

                        <input type="hidden" name="parent_id" value="">

                        <div class="form-grid-3col">
                            <div class="form-group">
                                <label for="name" class="form-label required">Nome</label>
                                <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars((string) ($editCategory['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                                <small class="form-text">Nome usado no cadastro, filtros e relatorios de produtos.</small>
                            </div>
                            <div class="form-group">
                                <label for="code" class="form-label">Codigo da categoria</label>
                                <input type="text" id="code" name="code" class="form-control" maxlength="12" value="<?php echo htmlspecialchars((string) ($editCategory['code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                <small class="form-text">Opcional. Apelido curto para uso interno.</small>
                            </div>
                            <div class="form-group">
                                <label for="status" class="form-label required">Status</label>
                                <select id="status" name="status" class="form-control" required>
                                    <option value="active" <?php echo ($editCategory['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Ativa</option>
                                    <option value="inactive" <?php echo ($editCategory['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inativa</option>
                                </select>
                                <small class="form-text">Categorias inativas deixam de aparecer nos novos cadastros.</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description" class="form-label">Descricao</label>
                            <input type="text" id="description" name="description" class="form-control" value="<?php echo htmlspecialchars((string) ($editCategory['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            <small class="form-text">Opcional. Explique que tipo de produto entra nesta categoria.</small>
                        </div>

                        <div class="form-actions">
                            <?php if ($editCategory): ?>
                                <a href="index.php?page=categories" class="btn btn-secondary">Cancelar</a>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-success">Salvar</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Lista de Categorias</h3></div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr><th>Nome</th><th>Codigo</th><th>Status</th><th>Acoes</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($categories)): ?>
                                    <tr><td colspan="4" class="text-center">Nenhuma categoria encontrada.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($categories as $category): ?>
                                        <?php require APP_PATH . '/views/products/category-row.php'; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once APP_PATH . '/views/templates/footer.php'; ?>
