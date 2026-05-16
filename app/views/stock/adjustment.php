<?php
$pageTitle = 'Ajuste de Estoque';
require_once APP_PATH . '/views/templates/header.php';
?>

<div class="app-container">
    <?php require_once APP_PATH . '/views/templates/navigation.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left"><h1 class="topbar-title">Ajuste de Estoque</h1></div>
            <div class="topbar-right"><a href="index.php?page=stock&action=movements" class="btn btn-secondary">Voltar</a></div>
        </header>

        <div class="content-area">
            <div class="card">
                <div class="card-body">
                    <form action="index.php?page=stock&action=saveAdjustment" method="POST" data-validate>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

                        <div class="form-group">
                            <label for="product_id" class="form-label required">Produto</label>
                            <select id="product_id" name="product_id" class="form-control" required>
                                <option value="">Selecione um produto...</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?php echo (int) $product['id']; ?>">
                                        <?php echo htmlspecialchars($product['name']); ?>
                                        (<?php echo htmlspecialchars($product['sku']); ?> | estoque <?php echo formatQuantity($product['quantity']); ?> <?php echo htmlspecialchars($product['unit_of_measure']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-grid-2col">
                            <div class="form-group">
                                <label for="physical_quantity" class="form-label required">Quantidade fisica contada</label>
                                <input type="number" id="physical_quantity" name="physical_quantity" class="form-control" min="0" step="0.001" required>
                            </div>
                            <div class="form-group">
                                <label for="reason" class="form-label required">Motivo</label>
                                <select id="reason" name="reason" class="form-control" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($reasons as $reason): ?>
                                        <option value="<?php echo htmlspecialchars($reason); ?>"><?php echo htmlspecialchars($reason); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="reauth_password" class="form-label required">Confirme sua senha</label>
                            <input type="password" id="reauth_password" name="reauth_password" class="form-control" autocomplete="current-password" required>
                        </div>

                        <div class="form-actions">
                            <a href="index.php?page=stock&action=movements" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-success">Registrar Ajuste</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once APP_PATH . '/views/templates/footer.php'; ?>
