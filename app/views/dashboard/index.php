<?php
$pageTitle = 'Dashboard';
require_once APP_PATH . '/views/templates/header.php';
?>

<div class="app-container">
    <?php require_once APP_PATH . '/views/templates/navigation.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left"><h1 class="topbar-title">Dashboard</h1></div>
            <div class="topbar-right">
                <div class="user-menu">
                    <div class="user-avatar"><?php echo strtoupper(substr(getCurrentUser()['name'], 0, 1)); ?></div>
                    <div>
                        <div style="font-weight: 600; font-size: var(--text-sm);"><?php echo htmlspecialchars(getCurrentUser()['name']); ?></div>
                        <div style="font-size: var(--text-xs); color: var(--neutral-600);"><?php echo USER_ROLES[getCurrentUser()['role']] ?? getCurrentUser()['role']; ?></div>
                    </div>
                </div>
            </div>
        </header>

        <div class="content-area">
            <div class="stats-grid">
                <div class="stat-card success">
                    <div class="stat-header">
                        <div>
                            <div class="stat-label">Faturamento do dia</div>
                            <div class="stat-value"><?php echo formatMoney($stats['today_revenue']); ?></div>
                        </div>
                    </div>
                    <div class="stat-description">
                        <?php echo formatNumber($stats['today_sales']); ?> venda(s), ticket medio <?php echo formatMoney($stats['today_ticket']); ?>.
                        Forma mais usada: <?php echo $stats['today_top_payment_method'] ? (PAYMENT_METHODS[$stats['today_top_payment_method']] ?? htmlspecialchars($stats['today_top_payment_method'])) : '-'; ?>
                    </div>
                </div>

                <div class="stat-card info">
                    <div class="stat-header">
                        <div>
                            <div class="stat-label">Faturamento do mes</div>
                            <div class="stat-value"><?php echo formatMoney($stats['month_revenue']); ?></div>
                        </div>
                    </div>
                    <div class="stat-description">
                        <?php echo formatMoney($stats['month_revenue_delta']); ?>
                        (<?php echo formatNumber($stats['month_revenue_delta_percent'], 1); ?>%) vs. mes anterior
                    </div>
                </div>

                <div class="stat-card <?php echo $stats['low_stock_count'] > 0 ? 'warning' : 'success'; ?>">
                    <div class="stat-header">
                        <div>
                            <div class="stat-label">Abaixo do minimo</div>
                            <div class="stat-value"><?php echo formatNumber($stats['critical_stock_count']); ?></div>
                        </div>
                    </div>
                    <div class="stat-description"><?php echo formatNumber($stats['low_stock_count']); ?> produto(s) para reposicao</div>
                </div>

                <div class="stat-card <?php echo $stats['payables_7_days'] > 0 ? 'warning' : 'success'; ?>">
                    <div class="stat-header">
                        <div>
                            <div class="stat-label">Contas em 7 dias</div>
                            <div class="stat-value"><?php echo formatMoney($stats['payables_7_days']); ?></div>
                        </div>
                    </div>
                    <div class="stat-description"><?php echo formatNumber($stats['open_cash_count']); ?> caixa(s) aberto(s)</div>
                </div>

                <div class="stat-card info">
                    <div class="stat-label">Margem bruta do mes</div>
                    <div class="stat-value"><?php echo formatMoney($stats['month_margin']); ?></div>
                    <div class="stat-description">CMV <?php echo formatMoney($stats['month_cmv']); ?></div>
                </div>

                <div class="stat-card <?php echo $stats['overdue_credit_amount'] > 0 ? 'warning' : 'success'; ?>">
                    <div class="stat-label">Crediario vencido</div>
                    <div class="stat-value"><?php echo formatMoney($stats['overdue_credit_amount']); ?></div>
                    <div class="stat-description">Parcelas vencidas de clientes</div>
                </div>

                <div class="stat-card info">
                    <div class="stat-label">Valor em estoque (custo)</div>
                    <div class="stat-value"><?php echo formatMoney($stats['stock_cost_value']); ?></div>
                    <div class="stat-description">Custo x quantidade dos produtos ativos</div>
                </div>

                <div class="stat-card success">
                    <div class="stat-label">Potencial de venda</div>
                    <div class="stat-value"><?php echo formatMoney($stats['stock_sale_potential']); ?></div>
                    <div class="stat-description">Venda x quantidade; margem potencial <?php echo formatMoney($stats['stock_potential_margin']); ?></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Faturamento dos ultimos 30 dias</h3></div>
                <div class="card-body">
                    <?php
                    $maxDailyRevenue = max(array_map(static fn($row) => (float) $row['revenue'], $stats['daily_revenue_30_days'] ?: [['revenue' => 0]]));
                    ?>
                    <div class="dashboard-bars" aria-label="Faturamento dos ultimos 30 dias">
                        <?php foreach ($stats['daily_revenue_30_days'] as $day): ?>
                            <?php $height = $maxDailyRevenue > 0 ? max(4, ((float) $day['revenue'] / $maxDailyRevenue) * 100) : 4; ?>
                            <div class="dashboard-bar-item" title="<?php echo formatDate($day['date'], 'd/m/Y'); ?> - <?php echo formatMoney((float) $day['revenue']); ?>">
                                <span class="dashboard-bar" style="height: <?php echo number_format($height, 2, '.', ''); ?>%;"></span>
                                <small><?php echo formatDate($day['date'], 'd/m'); ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($stats['low_stock_products'])): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Fila de reposicao</h3>
                    <a href="index.php?page=stock&action=lowStock" class="btn btn-sm btn-outline">Ver todos</a>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th>Codigo de barras</th>
                                    <th>Estoque</th>
                                    <th>Minimo</th>
                                    <th>Comprar</th>
                                    <th>Situacao</th>
                                    <th>Fornecedor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['low_stock_products'] as $product): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($product['sku']); ?></td>
                                        <td><?php echo formatQuantity($product['quantity']); ?></td>
                                        <td><?php echo formatQuantity($product['min_quantity']); ?></td>
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

            <div class="card">
                <div class="card-header"><h3 class="card-title">Ultimas vendas</h3></div>
                <div class="card-body">
                    <?php if (empty($stats['recent_sales'])): ?>
                        <div class="alert alert-info">Nenhuma venda registrada ainda.</div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Venda</th>
                                        <th>Cliente</th>
                                        <th>Operador</th>
                                        <th>Data</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats['recent_sales'] as $sale): ?>
                                        <tr>
                                            <td><a href="index.php?page=sales&action=view&id=<?php echo (int) $sale['id']; ?>"><?php echo htmlspecialchars($sale['sale_number']); ?></a></td>
                                            <td><?php echo htmlspecialchars($sale['customer_name'] ?? 'Consumidor Final'); ?></td>
                                            <td><?php echo htmlspecialchars($sale['user_name'] ?? '-'); ?></td>
                                            <td><?php echo formatDate($sale['created_at']); ?></td>
                                            <td><?php echo formatMoney((float) $sale['total_amount']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($stats['top_products'])): ?>
            <div class="card">
                <div class="card-header"><h3 class="card-title">Produtos mais vendidos no mes</h3></div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="table">
                            <thead><tr><th>Produto</th><th>Quantidade</th><th>Faturamento</th></tr></thead>
                            <tbody>
                                <?php foreach ($stats['top_products'] as $product): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                        <td><?php echo formatQuantity($product['sold_quantity']); ?></td>
                                        <td><?php echo formatMoney((float) $product['revenue']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Distribuicao de pagamentos no mes</h3></div>
                <div class="card-body">
                    <?php if (empty($stats['payment_distribution'])): ?>
                        <div class="alert alert-info">Nenhum pagamento registrado no mes.</div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="table">
                                <thead><tr><th>Forma</th><th>Total</th></tr></thead>
                                <tbody>
                                    <?php foreach ($stats['payment_distribution'] as $payment): ?>
                                        <tr>
                                            <td><?php echo PAYMENT_METHODS[$payment['payment_method']] ?? htmlspecialchars($payment['payment_method']); ?></td>
                                            <td><?php echo formatMoney((float) $payment['amount']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($stats['stopped_products'])): ?>
            <div class="card">
                <div class="card-header"><h3 class="card-title">Produtos parados</h3></div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="table">
                            <thead><tr><th>Produto</th><th>Codigo de barras</th><th>Estoque</th><th>Imobilizado</th><th>Ultima movimentacao</th></tr></thead>
                            <tbody>
                                <?php foreach ($stats['stopped_products'] as $product): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td><?php echo htmlspecialchars($product['sku']); ?></td>
                                        <td><?php echo formatQuantity($product['quantity']); ?></td>
                                        <td><?php echo formatMoney((float) $product['quantity'] * (float) $product['cost_price']); ?></td>
                                        <td><?php echo formatDate($product['last_movement'] ?? null); ?></td>
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
