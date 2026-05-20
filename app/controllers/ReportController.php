<?php
/**
 * Relatorios gerenciais basicos: inventario, ABC, giro, margem, perdas e DRE.
 */

class ReportController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function index(): void {
        Security::checkPermissions('manager');
        [$startDate, $endDate] = $this->period();

        $inventory = $this->inventory();
        $abcRevenue = $this->abc($startDate, $endDate, 'revenue');
        $abcMargin = $this->abc($startDate, $endDate, 'margin_amount');
        $turnover = $this->turnover($startDate, $endDate);
        $losses = $this->losses($startDate, $endDate);
        $dre = $this->dre($startDate, $endDate);

        require_once APP_PATH . '/views/reports/index.php';
    }

    public function exportCsv(): void {
        Security::checkPermissions('manager');
        [$startDate, $endDate] = $this->period();
        $type = $_GET['type'] ?? 'inventory';
        $rows = $this->rowsForType($type, $startDate, $endDate);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="relatorio_' . preg_replace('/[^a-z_]/', '', $type) . '.csv"');
        $out = fopen('php://output', 'w');
        if (!empty($rows)) {
            fputcsv($out, array_keys($rows[0]), ';');
            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }
        }
        fclose($out);
        exit;
    }

    public function exportPdf(): void {
        Security::checkPermissions('manager');
        [$startDate, $endDate] = $this->period();
        $type = $_GET['type'] ?? 'inventory';
        [$title, $columns] = $this->columnsForType($type);
        $rows = $this->rowsForType($type, $startDate, $endDate);
        $html = $this->renderReportHtml($title, $columns, $rows, substr($startDate, 0, 10), substr($endDate, 0, 10));
        $this->outputPdf('relatorio_' . preg_replace('/[^a-z_]/', '', $type) . '.pdf', $html);
    }

    private function period(): array {
        $start = $_GET['start_date'] ?? date('Y-m-01');
        $end = $_GET['end_date'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
            $start = date('Y-m-01');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            $end = date('Y-m-d');
        }
        return [$start . ' 00:00:00', $end . ' 23:59:59'];
    }

    private function inventory(): array {
        return $this->pdo->query("
            SELECT p.sku, p.name, COALESCE(c.name, '-') AS category_name,
                   p.quantity, p.cost_price,
                   ROUND(p.quantity * p.cost_price, 2) AS stock_cost_value,
                   p.sale_price,
                   ROUND(p.quantity * p.sale_price, 2) AS stock_sale_value
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.status = 'active'
            ORDER BY p.name ASC
        ")->fetchAll();
    }

    private function abc(string $startDate, string $endDate, string $criterion): array {
        $allowedCriteria = ['revenue', 'margin_amount'];
        if (!in_array($criterion, $allowedCriteria, true)) {
            $criterion = 'revenue';
        }

        $stmt = $this->pdo->prepare("
            SELECT product_id, sku, name,
                   ROUND(SUM(net_quantity), 3) AS sold_quantity,
                   ROUND(SUM(net_revenue), 2) AS revenue,
                   ROUND(SUM(net_revenue - net_cmv), 2) AS margin_amount
            FROM (" . $this->netSaleLinesSql() . ") net_sales
            GROUP BY product_id, sku, name
            HAVING SUM(net_quantity) > 0 OR SUM(net_revenue) > 0
            ORDER BY $criterion DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $rows = $stmt->fetchAll();
        $total = array_sum(array_map(static fn($row) => (float) $row[$criterion], $rows));
        $running = 0.0;
        foreach ($rows as &$row) {
            $running += (float) $row[$criterion];
            $percent = $total > 0 ? ($running / $total) * 100 : 0;
            $row['abc_class'] = $percent <= 80 ? 'A' : ($percent <= 95 ? 'B' : 'C');
        }
        unset($row);
        return $rows;
    }

    private function turnover(string $startDate, string $endDate): array {
        $stmt = $this->pdo->prepare("
            SELECT base.sku,
                   base.name,
                   base.category_name,
                   base.current_stock,
                   base.sold_quantity,
                   ROUND(base.sold_quantity / NULLIF(((base.initial_stock + base.ending_stock) / 2), 0), 3) AS turnover_rate,
                   ROUND(base.current_stock * base.cost_price, 2) AS immobilized_value
            FROM (
                SELECT p.id,
                       p.sku,
                       p.name,
                       COALESCE(c.name, '-') AS category_name,
                       p.quantity AS current_stock,
                       p.cost_price,
                       COALESCE(sold.sold_quantity, 0) AS sold_quantity,
                       COALESCE(
                           (
                               SELECT sm_before.new_quantity
                               FROM stock_movements sm_before
                               WHERE sm_before.product_id = p.id
                                 AND sm_before.date < ?
                               ORDER BY sm_before.date DESC, sm_before.id DESC
                               LIMIT 1
                           ),
                           (
                               SELECT sm_first.old_quantity
                               FROM stock_movements sm_first
                               WHERE sm_first.product_id = p.id
                                 AND sm_first.date BETWEEN ? AND ?
                               ORDER BY sm_first.date ASC, sm_first.id ASC
                               LIMIT 1
                           ),
                           p.quantity
                       ) AS initial_stock,
                       COALESCE(
                           (
                               SELECT sm_end.new_quantity
                               FROM stock_movements sm_end
                               WHERE sm_end.product_id = p.id
                                 AND sm_end.date <= ?
                               ORDER BY sm_end.date DESC, sm_end.id DESC
                               LIMIT 1
                           ),
                           p.quantity
                       ) AS ending_stock
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN (
                    SELECT product_id, SUM(net_quantity) AS sold_quantity
                    FROM (" . $this->netSaleLinesSql() . ") net_sales
                    GROUP BY product_id
                ) sold ON sold.product_id = p.id
                WHERE p.status = 'active'
            ) base
            ORDER BY turnover_rate ASC, immobilized_value DESC
        ");
        $stmt->execute([$startDate, $startDate, $endDate, $endDate, $startDate, $endDate]);
        return $stmt->fetchAll();
    }

    private function losses(string $startDate, string $endDate): array {
        $stmt = $this->pdo->prepare("
            SELECT p.sku, p.name, m.reason, ROUND(SUM(m.quantity), 3) AS quantity,
                   ROUND(SUM(m.quantity * COALESCE(m.unit_cost, p.cost_price)), 2) AS cost_value
            FROM stock_movements m
            LEFT JOIN products p ON p.id = m.product_id
            WHERE m.date BETWEEN ? AND ?
              AND (m.type = 'loss' OR m.is_theft_loss = 1 OR m.reason IN ('Quebra/Dano','Produto Vencido','Furto/Roubo'))
            GROUP BY p.sku, p.name, m.reason
            ORDER BY cost_value DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll();
    }

    private function dre(string $startDate, string $endDate): array {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(total_amount), 0)
            FROM sales
            WHERE status = 'completed'
              AND created_at BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate]);
        $grossRevenue = (float) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(net_cmv), 0)
            FROM (" . $this->netSaleLinesSql() . ") net_sales
        ");
        $stmt->execute([$startDate, $endDate]);
        $cmv = (float) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("
            SELECT ABS(COALESCE(SUM(fl.amount), 0))
            FROM financial_ledger fl
            INNER JOIN sales s ON s.id = fl.source_id
            WHERE fl.entry_type = 'adjustment'
              AND fl.source_table = 'sales'
              AND fl.amount < 0
              AND fl.description LIKE 'Devolucao parcial%'
              AND s.status = 'completed'
              AND fl.created_at BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate]);
        $deductions = (float) $stmt->fetchColumn();

        return [
            'gross_revenue' => $grossRevenue,
            'deductions' => $deductions,
            'net_revenue' => max(0, $grossRevenue - $deductions),
            'cmv' => $cmv,
            'gross_profit' => max(0, $grossRevenue - $deductions) - $cmv,
        ];
    }

    private function netSaleLinesSql(): string {
        return "
            SELECT grouped.product_id,
                   grouped.sku,
                   grouped.name,
                   CASE
                       WHEN grouped.sold_quantity - COALESCE(returns.returned_quantity, 0) > 0
                       THEN grouped.sold_quantity - COALESCE(returns.returned_quantity, 0)
                       ELSE 0
                   END AS net_quantity,
                   CASE
                       WHEN grouped.sold_quantity > 0
                       THEN grouped.gross_revenue *
                            (CASE
                                WHEN grouped.sold_quantity - COALESCE(returns.returned_quantity, 0) > 0
                                THEN grouped.sold_quantity - COALESCE(returns.returned_quantity, 0)
                                ELSE 0
                             END / grouped.sold_quantity)
                       ELSE 0
                   END AS net_revenue,
                   CASE
                       WHEN grouped.sold_quantity > 0
                       THEN grouped.gross_cmv *
                            (CASE
                                WHEN grouped.sold_quantity - COALESCE(returns.returned_quantity, 0) > 0
                                THEN grouped.sold_quantity - COALESCE(returns.returned_quantity, 0)
                                ELSE 0
                             END / grouped.sold_quantity)
                       ELSE 0
                   END AS net_cmv
            FROM (
                SELECT si.id AS sale_item_id,
                       s.id AS sale_id,
                       si.product_id,
                       si.sku_snapshot AS sku,
                       si.product_name AS name,
                       si.quantity AS sold_quantity,
                       si.line_total AS gross_revenue,
                       si.cost_price * si.quantity AS gross_cmv
                FROM sale_items si
                INNER JOIN sales s ON s.id = si.sale_id
                WHERE s.status = 'completed'
                  AND s.created_at BETWEEN ? AND ?
            ) grouped
            LEFT JOIN (
                SELECT sale_item_id, SUM(quantity) AS returned_quantity
                FROM stock_movements
                WHERE type = 'return'
                  AND sale_item_id IS NOT NULL
                  AND reason LIKE 'Devolucao parcial:%'
                GROUP BY sale_item_id
            ) returns ON returns.sale_item_id = grouped.sale_item_id
        ";
    }

    private function rowsForType(string $type, string $startDate, string $endDate): array {
        return match ($type) {
            'abc_revenue' => $this->abc($startDate, $endDate, 'revenue'),
            'abc_margin' => $this->abc($startDate, $endDate, 'margin_amount'),
            'turnover' => $this->turnover($startDate, $endDate),
            'losses' => $this->losses($startDate, $endDate),
            default => $this->inventory(),
        };
    }

    private function columnsForType(string $type): array {
        $definitions = [
            'inventory' => ['Inventario valorado', ['sku' => 'Codigo de barras', 'name' => 'Produto', 'category_name' => 'Categoria', 'quantity' => 'Qtd.', 'cost_price' => 'Custo', 'stock_cost_value' => 'Valor em estoque (custo)', 'sale_price' => 'Venda', 'stock_sale_value' => 'Potencial de venda']],
            'abc_revenue' => ['Curva ABC por faturamento', ['sku' => 'Codigo de barras', 'name' => 'Produto', 'sold_quantity' => 'Qtd.', 'revenue' => 'Faturamento', 'margin_amount' => 'Margem R$', 'abc_class' => 'ABC']],
            'abc_margin' => ['Curva ABC por margem', ['sku' => 'Codigo de barras', 'name' => 'Produto', 'sold_quantity' => 'Qtd.', 'revenue' => 'Faturamento', 'margin_amount' => 'Margem R$', 'abc_class' => 'ABC']],
            'turnover' => ['Giro de estoque', ['sku' => 'Codigo de barras', 'name' => 'Produto', 'category_name' => 'Categoria', 'current_stock' => 'Estoque', 'sold_quantity' => 'Vendido', 'turnover_rate' => 'Giro', 'immobilized_value' => 'Imobilizado']],
            'losses' => ['Relatorio de perdas', ['sku' => 'Codigo de barras', 'name' => 'Produto', 'reason' => 'Motivo', 'quantity' => 'Qtd.', 'cost_value' => 'Valor custo']],
        ];
        return $definitions[$type] ?? $definitions['inventory'];
    }

    private function renderReportHtml(string $title, array $columns, array $rows, string $startDate, string $endDate): string {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <title><?php echo htmlspecialchars($title); ?></title>
            <style>
                body { font-family: Arial, sans-serif; color: #1f2937; font-size: 12px; }
                h1 { font-size: 20px; margin-bottom: 4px; }
                .meta { color: #6b7280; margin-bottom: 16px; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #d1d5db; padding: 6px; text-align: left; }
                th { background: #f3f4f6; }
                .notice { margin-top: 16px; font-size: 11px; color: #6b7280; }
            </style>
        </head>
        <body>
            <h1><?php echo htmlspecialchars(APP_NAME . ' - ' . $title); ?></h1>
            <div class="meta">Periodo: <?php echo htmlspecialchars(formatDate($startDate, 'd/m/Y') . ' a ' . formatDate($endDate, 'd/m/Y')); ?></div>
            <table>
                <thead>
                    <tr>
                        <?php foreach ($columns as $label): ?>
                            <th><?php echo htmlspecialchars($label); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($columns as $key => $label): ?>
                                <?php $value = $row[$key] ?? '-'; ?>
                                <?php $isMoney = (is_numeric($value) && str_contains((string) $key, 'value')) || in_array($key, ['cost_price', 'sale_price', 'revenue', 'margin_amount', 'immobilized_value', 'cost_value'], true); ?>
                                <td><?php echo $isMoney ? htmlspecialchars(formatMoney((float) $value)) : htmlspecialchars((string) $value); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="notice">Este documento nao possui valor fiscal.</div>
        </body>
        </html>
        <?php
        return (string) ob_get_clean();
    }

    private function outputPdf(string $filename, string $html): void {
        $autoload = BASE_PATH . '/vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }

        if (class_exists('\\Mpdf\\Mpdf')) {
            $tmpDir = DATA_PATH . '/tmp';
            if (!is_dir($tmpDir)) {
                mkdir($tmpDir, 0755, true);
            }
            $mpdf = new \Mpdf\Mpdf(['tempDir' => $tmpDir]);
            $mpdf->WriteHTML($html);
            $mpdf->Output($filename, 'D');
            exit;
        }

        require_once APP_PATH . '/lib/SimplePdf.php';
        SimplePdf::download($filename, $html);
    }
}
