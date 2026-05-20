<?php
/**
 * Dashboard de varejo da Ferragens Souza.
 */

class DashboardController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function index(): void {
        if (!hasPermission('operator')) {
            redirect('pos');
        }

        $stats = [];
        $today = dbToday();
        $todayStart = $today . ' 00:00:00';
        $tomorrowStart = (new DateTimeImmutable('tomorrow'))->format('Y-m-d 00:00:00');
        $now = dbNow();
        $monthStart = dbMonthStart();
        $previousMonthStart = (new DateTimeImmutable('first day of last month 00:00:00'))->format('Y-m-d H:i:s');
        $previousMonthEnd = (new DateTimeImmutable('last day of last month 23:59:59'))->format('Y-m-d H:i:s');
        $sevenDaysAhead = dbDatePlusDays(7);

        $stats['today_revenue'] = (float) $this->scalar("
            SELECT COALESCE(SUM(total_amount), 0)
            FROM sales
            WHERE status = 'completed'
              AND created_at >= ?
              AND created_at < ?
        ", [$todayStart, $tomorrowStart]) - $this->returnDeductions($todayStart, $tomorrowStart);
        $stats['today_sales'] = (int) $this->scalar("
            SELECT COUNT(*)
            FROM sales
            WHERE status = 'completed'
              AND created_at >= ?
              AND created_at < ?
        ", [$todayStart, $tomorrowStart]);
        $stats['today_ticket'] = $stats['today_sales'] > 0 ? $stats['today_revenue'] / $stats['today_sales'] : 0;
        $topPaymentToday = $this->query("
            SELECT sp.payment_method, COUNT(*) AS usage_count
            FROM sale_payments sp
            INNER JOIN sales s ON s.id = sp.sale_id
            WHERE s.status = 'completed'
              AND s.created_at >= ?
              AND s.created_at < ?
            GROUP BY sp.payment_method
            ORDER BY usage_count DESC, sp.payment_method ASC
            LIMIT 1
        ", [$todayStart, $tomorrowStart]);
        $stats['today_top_payment_method'] = $topPaymentToday[0]['payment_method'] ?? null;

        $stats['month_revenue'] = (float) $this->scalar("
            SELECT COALESCE(SUM(total_amount), 0)
            FROM sales
            WHERE status = 'completed'
              AND created_at >= ?
        ", [$monthStart]) - $this->returnDeductions($monthStart, $now);
        $stats['previous_month_revenue'] = (float) $this->scalar("
            SELECT COALESCE(SUM(total_amount), 0)
            FROM sales
            WHERE status = 'completed'
              AND created_at BETWEEN ? AND ?
        ", [$previousMonthStart, $previousMonthEnd]) - $this->returnDeductions($previousMonthStart, $previousMonthEnd);
        $stats['month_revenue_delta'] = $stats['month_revenue'] - $stats['previous_month_revenue'];
        $stats['month_revenue_delta_percent'] = $stats['previous_month_revenue'] > 0
            ? ($stats['month_revenue_delta'] / $stats['previous_month_revenue']) * 100
            : 0;
        $stats['month_cmv'] = (float) $this->scalar("
            SELECT COALESCE(SUM(net_cmv), 0)
            FROM (" . $this->netSaleLinesSql() . ") net_sales
        ", [$monthStart, $now]);
        $stats['month_margin'] = $stats['month_revenue'] - $stats['month_cmv'];
        $stats['open_cash_count'] = (int) $this->scalar("SELECT COUNT(*) FROM cash_registers WHERE status = 'open'");
        $stats['low_stock_count'] = (int) $this->scalar("
            SELECT COUNT(*)
            FROM products
            WHERE status = 'active'
              AND (
                  (min_quantity > 0 AND quantity < min_quantity)
                  OR (reorder_point > 0 AND quantity < reorder_point)
              )
        ");
        $stats['critical_stock_count'] = (int) $this->scalar('SELECT COUNT(*) FROM products WHERE status = "active" AND min_quantity > 0 AND quantity < min_quantity');
        $stats['payables_7_days'] = (float) $this->scalar("
            SELECT COALESCE(SUM(amount - paid_amount), 0)
            FROM accounts_payable
            WHERE status IN ('open','partial')
              AND due_date BETWEEN ? AND ?
        ", [$today, $sevenDaysAhead]);
        $stats['overdue_credit_amount'] = (float) $this->scalar("
            SELECT COALESCE(SUM(amount - received_amount), 0)
            FROM accounts_receivable
            WHERE source = 'store_credit'
              AND status IN ('open','partial')
              AND due_date < ?
        ", [$today]);
        $inventoryTotals = $this->query("
            SELECT COALESCE(SUM(cost_price * quantity), 0) AS stock_cost_value,
                   COALESCE(SUM(sale_price * quantity), 0) AS stock_sale_potential
            FROM products
            WHERE status = 'active'
        ");
        $stats['stock_cost_value'] = (float) ($inventoryTotals[0]['stock_cost_value'] ?? 0);
        $stats['stock_sale_potential'] = (float) ($inventoryTotals[0]['stock_sale_potential'] ?? 0);
        $stats['stock_potential_margin'] = $stats['stock_sale_potential'] - $stats['stock_cost_value'];

        $stats['low_stock_products'] = $this->query("
            SELECT p.id, p.sku, p.name, p.quantity, p.min_quantity, p.reorder_point,
                   CASE WHEN p.reorder_point > p.min_quantity THEN p.reorder_point ELSE p.min_quantity END AS reorder_target,
                   s.name AS supplier_name
            FROM products p
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            WHERE p.status = 'active'
              AND (
                  (p.min_quantity > 0 AND p.quantity < p.min_quantity)
                  OR (p.reorder_point > 0 AND p.quantity < p.reorder_point)
              )
            ORDER BY
                CASE WHEN p.min_quantity > 0 AND p.quantity < p.min_quantity THEN 0 ELSE 1 END ASC,
                (CASE WHEN p.reorder_point > p.min_quantity THEN p.reorder_point ELSE p.min_quantity END - p.quantity) DESC,
                p.quantity ASC
            LIMIT 10
        ");

        $stats['recent_sales'] = $this->query("
            SELECT s.id, s.sale_number, s.total_amount, s.created_at, c.name AS customer_name, u.name AS user_name
            FROM sales s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN users u ON s.user_id = u.id
            ORDER BY s.created_at DESC
            LIMIT 8
        ");

        $stats['top_products'] = $this->query("
            SELECT name AS product_name, SUM(net_quantity) AS sold_quantity, SUM(net_revenue) AS revenue
            FROM (" . $this->netSaleLinesSql() . ") net_sales
            GROUP BY product_id, name
            HAVING SUM(net_quantity) > 0
            ORDER BY sold_quantity DESC
            LIMIT 5
        ", [$monthStart, $now]);

        $stats['stopped_products'] = $this->query("
            SELECT p.id, p.sku, p.name, p.quantity, p.cost_price,
                   MAX(m.date) AS last_movement
            FROM products p
            LEFT JOIN stock_movements m ON m.product_id = p.id
            WHERE p.status = 'active'
            GROUP BY p.id, p.sku, p.name, p.quantity, p.cost_price
            HAVING last_movement IS NULL OR last_movement < ?
            ORDER BY last_movement ASC
            LIMIT 5
        ", [(new DateTimeImmutable('-90 days'))->format('Y-m-d H:i:s')]);

        $stats['payment_distribution'] = $this->query("
            SELECT cm.payment_method,
                   COALESCE(SUM(CASE WHEN cm.type = 'refund' THEN -cm.amount ELSE cm.amount END), 0) AS amount
            FROM cash_movements cm
            INNER JOIN sales s ON s.id = cm.sale_id
            WHERE s.status = 'completed'
              AND cm.type IN ('sale', 'refund')
              AND cm.payment_method IS NOT NULL
              AND cm.created_at >= ?
            GROUP BY cm.payment_method
            HAVING amount <> 0
            ORDER BY amount DESC
        ", [$monthStart]);

        $dailyRows = $this->query("
            SELECT DATE(created_at) AS sale_date, COALESCE(SUM(total_amount), 0) AS revenue
            FROM sales
            WHERE status = 'completed'
              AND created_at >= ?
            GROUP BY DATE(created_at)
            ORDER BY sale_date ASC
        ", [(new DateTimeImmutable('-29 days 00:00:00'))->format('Y-m-d H:i:s')]);
        $dailyMap = [];
        foreach ($dailyRows as $row) {
            $dayStart = $row['sale_date'] . ' 00:00:00';
            $dayEnd = $row['sale_date'] . ' 23:59:59';
            $dailyMap[$row['sale_date']] = max(0.0, (float) $row['revenue'] - $this->returnDeductions($dayStart, $dayEnd));
        }
        $stats['daily_revenue_30_days'] = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = (new DateTimeImmutable('today'))->modify('-' . $i . ' days')->format('Y-m-d');
            $stats['daily_revenue_30_days'][] = [
                'date' => $date,
                'revenue' => $dailyMap[$date] ?? 0.0,
            ];
        }

        require_once APP_PATH . '/views/dashboard/index.php';
    }

    private function scalar(string $sql, array $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private function query(string $sql, array $params = []): array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function returnDeductions(string $startDate, string $endDate): float {
        return abs((float) $this->scalar("
            SELECT COALESCE(SUM(fl.amount), 0)
            FROM financial_ledger fl
            INNER JOIN sales s ON s.id = fl.source_id
            WHERE fl.entry_type = 'adjustment'
              AND fl.source_table = 'sales'
              AND fl.amount < 0
              AND fl.description LIKE 'Devolucao parcial%'
              AND s.status = 'completed'
              AND fl.created_at BETWEEN ? AND ?
        ", [$startDate, $endDate]));
    }

    // M-05: netSaleLinesSql unificada com ReportController (usa sale_item_id para devoluções corretas)
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
}
