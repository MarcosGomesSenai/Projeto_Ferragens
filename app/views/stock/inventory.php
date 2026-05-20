<?php
$pageTitle = 'Inventario';
require_once APP_PATH . '/views/templates/header.php';
?>

<div class="app-container">
    <?php require_once APP_PATH . '/views/templates/navigation.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left"><h1 class="topbar-title">Inventario</h1></div>
        </header>

        <div class="content-area">
            <div class="stats-grid">
                <div class="stat-card success">
                    <div class="stat-label">Valor em estoque (custo)</div>
                    <div class="stat-value"><?php echo formatMoney((float) ($stats['total_stock_value'] ?? 0)); ?></div>
                </div>
                <div class="stat-card info">
                    <div class="stat-label">Potencial de venda</div>
                    <div class="stat-value"><?php echo formatMoney((float) ($stats['total_sale_value'] ?? 0)); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total de produtos</div>
                    <div class="stat-value"><?php echo formatNumber((int) ($stats['total_items'] ?? 0)); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Quantidade total</div>
                    <div class="stat-value"><?php echo formatQuantity($stats['total_quantity'] ?? 0); ?></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Inventario valorado</h3></div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th>Categoria</th>
                                    <th>Qtd.</th>
                                    <th>Min.</th>
                                    <th>Custo</th>
                                    <th>Venda</th>
                                    <th>Total custo</th>
                                    <th>Total venda</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($products)): ?>
                                    <tr><td colspan="8" class="text-center">Nenhum produto encontrado.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($product['name']); ?></strong><small class="muted-line"><?php echo htmlspecialchars($product['unit_of_measure']); ?></small></td>
                                            <td><?php echo htmlspecialchars($product['category_name'] ?? '-'); ?></td>
                                            <td><?php echo formatQuantity($product['quantity']); ?></td>
                                            <td><?php echo formatQuantity($product['min_quantity']); ?></td>
                                            <td><?php echo formatMoney((float) $product['cost_price']); ?></td>
                                            <td><?php echo formatMoney((float) $product['sale_price']); ?></td>
                                            <td><?php echo formatMoney((float) $product['cost_price'] * (float) $product['quantity']); ?></td>
                                            <td><?php echo formatMoney((float) $product['sale_price'] * (float) $product['quantity']); ?></td>
                                        </tr>
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
