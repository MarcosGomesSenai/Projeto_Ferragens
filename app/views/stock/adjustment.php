<?php
$pageTitle = 'Nova Movimentacao';
require_once APP_PATH . '/views/templates/header.php';
?>

<div class="app-container">
    <?php require_once APP_PATH . '/views/templates/navigation.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left"><h1 class="topbar-title">Nova Movimentacao</h1></div>
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
                            <small class="form-text">Selecione o produto que tera o estoque ajustado.</small>
                        </div>

                        <div class="form-grid-2col">
                            <div class="form-group">
                                <label for="movement_type" class="form-label required">Tipo de movimentacao</label>
                                <select id="movement_type" name="movement_type" class="form-control" required>
                                    <?php foreach ($movementTypes as $type => $label): ?>
                                        <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text">Entrada soma no estoque. Retirada diminui do estoque.</small>
                            </div>
                            <div class="form-group">
                                <label for="quantity" class="form-label required" id="quantity_label">Quantidade movimentada</label>
                                <input type="number" id="quantity" name="quantity" class="form-control" min="0" step="0.001" required>
                                <small class="form-text" id="quantity_help">Informe quanto deve entrar no estoque.</small>
                            </div>
                        </div>

                        <div class="form-grid-2col">
                            <div class="form-group">
                                <label for="reason" class="form-label required">Motivo</label>
                                <select id="reason" name="reason" class="form-control" required>
                                    <option value="">Selecione...</option>
                                </select>
                                <small class="form-text">O motivo muda conforme Entrada ou Retirada.</small>
                            </div>
                        </div>

                        <div class="form-actions">
                            <a href="index.php?page=stock&action=movements" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-success">Registrar Movimentacao</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<script nonce="<?php echo htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '', ENT_QUOTES, 'UTF-8'); ?>">
document.addEventListener('DOMContentLoaded', function () {
    const typeSelect = document.getElementById('movement_type');
    const reasonSelect = document.getElementById('reason');
    const quantityLabel = document.getElementById('quantity_label');
    const quantityHelp = document.getElementById('quantity_help');
    const reasonsByType = <?php echo json_encode($reasonsByType, JSON_UNESCAPED_UNICODE); ?>;

    function refreshReasons() {
        const selectedReasons = reasonsByType[typeSelect.value] || [];
        reasonSelect.innerHTML = '<option value="">Selecione...</option>' + selectedReasons
            .map(reason => `<option value="${reason}">${reason}</option>`)
            .join('');
    }

    function refreshQuantityText() {
        const type = typeSelect.value;
        if (type === 'withdrawal') {
            quantityLabel.textContent = 'Quantidade retirada';
            quantityHelp.textContent = 'Informe quanto deve sair do estoque.';
        } else {
            quantityLabel.textContent = 'Quantidade de entrada';
            quantityHelp.textContent = 'Informe quanto deve entrar no estoque.';
        }
        refreshReasons();
    }

    typeSelect.addEventListener('change', refreshQuantityText);
    refreshQuantityText();
});
</script>

<?php require_once APP_PATH . '/views/templates/footer.php'; ?>
