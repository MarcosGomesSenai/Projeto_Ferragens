<?php
$pageTitle = 'Reposicao';
require_once APP_PATH . '/views/templates/header.php';
?>

<div class="app-container">
    <?php require_once APP_PATH . '/views/templates/navigation.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left"><h1 class="topbar-title">Fila de Reposicao</h1></div>
            <div class="topbar-right"><a href="index.php?page=stock&action=adjustment" class="btn btn-primary">Nova Movimentacao</a></div>
        </header>

        <div class="content-area">
            <?php if (empty($lowStockProducts)): ?>
                <div class="alert alert-success">Nenhum produto para reposicao no momento.</div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Produtos prioritarios</h3></div>
                    <div class="card-body">
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Produto</th>
                                        <th>Codigo de barras</th>
                                        <th>Categoria</th>
                                        <th>Estoque</th>
                                        <th>Min.</th>
                                        <th>Ponto reposicao</th>
                                        <th>Comprar</th>
                                        <th>Situacao</th>
                                        <th>Fornecedor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lowStockProducts as $product): ?>
                                        <?php $level = productStockAlertLevel($product); ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($product['sku']); ?></td>
                                            <td><?php echo htmlspecialchars($product['category_name'] ?? '-'); ?></td>
                                            <td><span class="badge <?php echo $level === 'critical' ? 'badge-error' : 'badge-warning'; ?>"><?php echo formatQuantity($product['quantity']); ?></span></td>
                                            <td><?php echo formatQuantity($product['min_quantity']); ?></td>
                                            <td><?php echo formatQuantity($product['reorder_point']); ?></td>
                                            <td><?php echo formatQuantity(productSuggestedReorderQuantity($product)); ?></td>
                                            <td><?php echo productStockAlertText($product); ?></td>
                                            <td><?php echo htmlspecialchars($product['supplier_name'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once APP_PATH . '/views/templates/footer.php'; ?>
