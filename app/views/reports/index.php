<?php
$pageTitle = 'Relatorios';
require_once APP_PATH . '/views/templates/header.php';
$startDateValue = substr($startDate, 0, 10);
$endDateValue = substr($endDate, 0, 10);
?>
<div class="app-container">
    <?php require_once APP_PATH . '/views/templates/navigation.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left"><h1 class="topbar-title">Relatorios</h1></div>
        </header>
        <div class="content-area">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="index.php" class="form-grid-3col">
                        <input type="hidden" name="page" value="reports">
                        <div class="form-group">
                            <label for="start_date" class="form-label">Data inicial</label>
                            <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($startDateValue); ?>">
                        </div>
                        <div class="form-group">
                            <label for="end_date" class="form-label">Data final</label>
                            <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($endDateValue); ?>">
                        </div>
                        <div class="form-actions form-actions-left">
                            <button type="submit" class="btn btn-primary">Filtrar</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card success"><div class="stat-label">Receita liquida</div><div class="stat-value"><?php echo formatMoney($dre['net_revenue']); ?></div></div>
                <div class="stat-card info"><div class="stat-label">CMV</div><div class="stat-value"><?php echo formatMoney($dre['cmv']); ?></div></div>
                <div class="stat-card warning"><div class="stat-label">Deducoes</div><div class="stat-value"><?php echo formatMoney($dre['deductions']); ?></div></div>
                <div class="stat-card success"><div class="stat-label">Lucro bruto</div><div class="stat-value"><?php echo formatMoney($dre['gross_profit']); ?></div></div>
            </div>

            <?php
            $tables = [
                'inventory' => ['Inventario valorado', $inventory, ['sku' => 'SKU', 'name' => 'Produto', 'category_name' => 'Categoria', 'quantity' => 'Qtd.', 'cost_price' => 'Custo', 'stock_cost_value' => 'Valor custo', 'sale_price' => 'Venda', 'stock_sale_value' => 'Valor venda']],
                'abc_revenue' => ['Curva ABC por faturamento', $abcRevenue, ['sku' => 'SKU', 'name' => 'Produto', 'sold_quantity' => 'Qtd.', 'revenue' => 'Faturamento', 'margin_amount' => 'Margem R$', 'abc_class' => 'ABC']],
                'abc_margin' => ['Curva ABC por margem', $abcMargin, ['sku' => 'SKU', 'name' => 'Produto', 'sold_quantity' => 'Qtd.', 'revenue' => 'Faturamento', 'margin_amount' => 'Margem R$', 'abc_class' => 'ABC']],
                'turnover' => ['Giro de estoque', $turnover, ['sku' => 'SKU', 'name' => 'Produto', 'category_name' => 'Categoria', 'current_stock' => 'Estoque', 'sold_quantity' => 'Vendido', 'turnover_rate' => 'Giro', 'immobilized_value' => 'Imobilizado']],
                'losses' => ['Relatorio de perdas', $losses, ['sku' => 'SKU', 'name' => 'Produto', 'reason' => 'Motivo', 'quantity' => 'Qtd.', 'cost_value' => 'Valor custo']],
            ];
            ?>

            <?php foreach ($tables as $type => [$title, $rows, $columns]): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><?php echo htmlspecialchars($title); ?></h3>
                        <div class="table-actions">
                            <a href="index.php?page=reports&action=exportCsv&type=<?php echo urlencode($type); ?>&start_date=<?php echo urlencode($startDateValue); ?>&end_date=<?php echo urlencode($endDateValue); ?>" class="btn btn-sm btn-outline">CSV</a>
                            <a href="index.php?page=reports&action=exportPdf&type=<?php echo urlencode($type); ?>&start_date=<?php echo urlencode($startDateValue); ?>&end_date=<?php echo urlencode($endDateValue); ?>" class="btn btn-sm btn-outline">PDF</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($rows)): ?>
                            <div class="alert alert-info">Nenhum dado no periodo.</div>
                        <?php else: ?>
                            <?php if (count($rows) > 50): ?>
                                <p class="text-muted mb-2" style="font-size:.875rem">
                                    Exibindo 50 de <?php echo count($rows); ?> resultados.
                                    Use <strong>Exportar CSV</strong> para ver todos.
                                </p>
                            <?php endif; ?>
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <?php foreach ($columns as $label): ?>
                                                <th><?php echo htmlspecialchars($label); ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($rows, 0, 50) as $row): ?>
                                            <tr>
                                                <?php foreach ($columns as $key => $label): ?>
                                                    <?php $value = $row[$key] ?? '-'; ?>
                                                    <?php $isMoney = (is_numeric($value) && str_contains((string) $key, 'value')) || in_array($key, ['cost_price', 'sale_price', 'revenue', 'margin_amount', 'immobilized_value'], true); ?>
                                                    <td>
                                                        <?php echo $isMoney
                                                            ? formatMoney((float) $value)
                                                            : htmlspecialchars((string) $value); ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
</div>

<?php require_once APP_PATH . '/views/templates/footer.php'; ?>
