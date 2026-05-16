<?php
/**
 * Consulta e cancelamento de vendas.
 */

class SaleController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function index(): void {
        Security::checkPermissions('manager');
        $sales = $this->pdo->query("
            SELECT s.*, c.name AS customer_name, c.is_default AS customer_is_default, u.name AS user_name
            FROM sales s
            LEFT JOIN customers c ON c.id = s.customer_id
            LEFT JOIN users u ON u.id = s.user_id
            ORDER BY s.created_at DESC
            LIMIT 100
        ")->fetchAll();
        require_once APP_PATH . '/views/sales/list.php';
    }

    public function view(): void {
        Security::checkPermissions('seller');
        $id = (int) ($_GET['id'] ?? 0);
        $sale = $this->findSale($id);
        if (!$sale) {
            setFlashMessage('error', 'Venda nao encontrada.');
            redirect(hasPermission('manager') ? 'sales' : 'pos');
        }

        $items = $this->pdo->prepare('SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id ASC');
        $items->execute([$id]);
        $saleItems = $items->fetchAll();

        $payments = $this->pdo->prepare('SELECT * FROM sale_payments WHERE sale_id = ? ORDER BY id ASC');
        $payments->execute([$id]);
        $salePayments = $payments->fetchAll();

        require_once APP_PATH . '/views/sales/view.php';
    }

    public function pdf(): void {
        Security::checkPermissions('seller');
        $id = (int) ($_GET['id'] ?? 0);
        $sale = $this->findSale($id);
        if (!$sale) {
            setFlashMessage('error', 'Venda nao encontrada.');
            redirect(hasPermission('manager') ? 'sales' : 'pos');
        }

        $itemsStmt = $this->pdo->prepare('SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id ASC');
        $itemsStmt->execute([$id]);
        $saleItems = $itemsStmt->fetchAll();

        $paymentsStmt = $this->pdo->prepare('SELECT * FROM sale_payments WHERE sale_id = ? ORDER BY id ASC');
        $paymentsStmt->execute([$id]);
        $salePayments = $paymentsStmt->fetchAll();

        $html = $this->renderSaleDocument($sale, $saleItems, $salePayments);
        $this->outputPdf('venda_' . preg_replace('/[^A-Za-z0-9_-]/', '', $sale['sale_number']) . '.pdf', $html);
    }

    public function cancel(): void {
        Security::checkPermissions('manager');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('sales');
        }
        Security::validateRequest();

        $id = (int) ($_POST['id'] ?? 0); // M-02: somente POST; remover ID da query string
        $reason = sanitize($_POST['reason'] ?? '');
        $sale = $this->findSale($id);
        if (!$sale) {
            setFlashMessage('error', 'Venda nao encontrada.');
            redirect('sales');
        }
        if ($sale['status'] === 'cancelled') {
            setFlashMessage('warning', 'Esta venda ja foi cancelada.');
            redirect('sales', ['action' => 'view', 'id' => $id]);
        }
        if ($reason === '') {
            setFlashMessage('error', 'Informe o motivo do cancelamento.');
            redirect('sales', ['action' => 'view', 'id' => $id]);
        }
        if (!Security::verifyCurrentPassword($_POST['reauth_password'] ?? '')) {
            setFlashMessage('error', 'Senha de confirmacao invalida.');
            redirect('sales', ['action' => 'view', 'id' => $id]);
        }

        $this->pdo->beginTransaction();
        try {
            $itemsStmt = $this->pdo->prepare('SELECT * FROM sale_items WHERE sale_id = ?');
            $itemsStmt->execute([$id]);
            $items = $itemsStmt->fetchAll();
            $timestamp = dbNow();

            foreach ($items as $item) {
                $productStmt = $this->pdo->prepare('SELECT quantity FROM products WHERE id = ?' . selectForUpdateClause());
                $productStmt->execute([$item['product_id']]);
                $currentQty = (float) $productStmt->fetchColumn();
                $newQty = round($currentQty + (float) $item['quantity'], 3);

                $this->pdo->prepare('UPDATE products SET quantity = ? WHERE id = ?')->execute([$newQty, $item['product_id']]);
                $this->pdo->prepare("
                    INSERT INTO stock_movements
                        (product_id, user_id, type, quantity, old_quantity, new_quantity, sale_id, reason, date)
                    VALUES (?, ?, 'return', ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $item['product_id'],
                    $_SESSION['user_id'],
                    $item['quantity'],
                    $currentQty,
                    $newQty,
                    $id,
                    'Cancelamento de venda',
                    $timestamp,
                ]);
            }

            $paymentStmt = $this->pdo->prepare('SELECT * FROM sale_payments WHERE sale_id = ?');
            $paymentStmt->execute([$id]);
            $payments = $paymentStmt->fetchAll();
            $refundCashRegister = $this->cashRegisterForRefund((int) $sale['cash_register_id']);

            foreach ($payments as $payment) {
                $refundAmount = $payment['payment_method'] === 'cash'
                    ? (float) $payment['amount'] - (float) $payment['change_amount']
                    : (float) $payment['amount'];
                $this->pdo->prepare("
                    INSERT INTO cash_movements
                        (cash_register_id, sale_id, user_id, type, payment_method, amount, reason, created_at)
                    VALUES (?, ?, ?, 'refund', ?, ?, 'Cancelamento de venda', ?)
                ")->execute([
                    $refundCashRegister['id'],
                    $id,
                    $_SESSION['user_id'],
                    $payment['payment_method'],
                    $refundAmount,
                    $timestamp,
                ]);

                if ($payment['payment_method'] === 'cash') {
                    $this->pdo->prepare("
                        UPDATE cash_registers
                        SET expected_balance = expected_balance - ?
                        WHERE id = ?
                    ")->execute([$refundAmount, $refundCashRegister['id']]);
                }

                if ($payment['payment_method'] === 'customer_credit' && $this->isRealCustomerSale($sale) && $refundAmount > 0) {
                    $this->pdo->prepare("
                        INSERT INTO customer_credits
                            (customer_id, sale_id, amount, used_amount, reason, status, created_by, created_at)
                        VALUES (?, ?, ?, 0, ?, 'open', ?, ?)
                    ")->execute([
                        (int) $sale['customer_id'],
                        $id,
                        round($refundAmount, 2),
                        'Restauracao de credito por cancelamento da venda ' . $sale['sale_number'],
                        $_SESSION['user_id'],
                        $timestamp,
                    ]);
                }
            }

            $this->pdo->prepare("
                UPDATE sales
                SET status = 'cancelled', cancel_reason = ?, cancelled_by = ?, cancelled_at = ?
                WHERE id = ?
            ")->execute([$reason, $_SESSION['user_id'], $timestamp, $id]);

            $this->pdo->prepare("UPDATE accounts_receivable SET status = 'cancelled' WHERE sale_id = ? AND status <> 'paid'")
                ->execute([$id]);

            $incomeStmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(amount), 0)
                FROM financial_ledger
                WHERE source_table = 'sales'
                  AND source_id = ?
                  AND entry_type = 'income'
            ");
            $incomeStmt->execute([$id]);
            $ledgerIncome = round((float) $incomeStmt->fetchColumn(), 2);
            if ($ledgerIncome > 0) {
                $this->pdo->prepare("
                    INSERT INTO financial_ledger (source_table, source_id, entry_type, amount, description, created_by, created_at)
                    VALUES ('sales', ?, 'adjustment', ?, ?, ?, ?)
                ")->execute([$id, -abs($ledgerIncome), 'Cancelamento de venda', $_SESSION['user_id'], $timestamp]);
            }

            $this->pdo->commit();
            Security::auditLog('sale_cancelled', [
                'module' => 'sales',
                'record_id' => (string) $id,
                'before' => ['status' => $sale['status']],
                'after' => ['status' => 'cancelled', 'reason' => $reason],
            ]);
            setFlashMessage('success', 'Venda cancelada com sucesso.');
            redirect('sales', ['action' => 'view', 'id' => $id]);
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            error_log('[Ferragens Souza] Erro ao cancelar venda: ' . $e->getMessage());
            setFlashMessage('error', 'Nao foi possivel cancelar a venda.');
            redirect('sales', ['action' => 'view', 'id' => $id]);
        }
    }

    public function returnItem(): void {
        Security::checkPermissions('manager');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('sales');
        }
        Security::validateRequest();

        $saleId = (int) ($_POST['sale_id'] ?? 0);
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $quantity = normalizeQuantity($_POST['quantity'] ?? 0);
        $reason = sanitize($_POST['reason'] ?? '');
        $refundMethod = $_POST['refund_method'] ?? 'cash_refund';

        if ($saleId <= 0 || $itemId <= 0 || $quantity <= 0 || $reason === '') {
            setFlashMessage('error', 'Preencha item, quantidade e motivo da devolucao.');
            redirect('sales', ['action' => 'view', 'id' => $saleId]);
        }
        if (!in_array($refundMethod, ['cash_refund', 'customer_credit'], true)) {
            setFlashMessage('error', 'Destino da devolucao invalido.');
            redirect('sales', ['action' => 'view', 'id' => $saleId]);
        }
        if (!Security::verifyCurrentPassword($_POST['reauth_password'] ?? '')) {
            setFlashMessage('error', 'Senha de confirmacao invalida.');
            redirect('sales', ['action' => 'view', 'id' => $saleId]);
        }

        $sale = $this->findSale($saleId);
        if (!$sale || $sale['status'] !== 'completed') {
            setFlashMessage('error', 'Venda nao permite devolucao parcial.');
            redirect('sales', ['action' => 'view', 'id' => $saleId]);
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM sale_items WHERE id = ? AND sale_id = ? LIMIT 1');
            $stmt->execute([$itemId, $saleId]);
            $item = $stmt->fetch();
            if (!$item) {
                throw new RuntimeException('Item da venda nao encontrado.');
            }

            $returnedStmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(quantity), 0)
                FROM stock_movements
                WHERE sale_id = ?
                  AND sale_item_id = ?
                  AND type = 'return'
                  AND reason LIKE 'Devolucao parcial:%'
            ");
            $returnedStmt->execute([$saleId, $itemId]);
            $alreadyReturned = (float) $returnedStmt->fetchColumn();
            $availableToReturn = round((float) $item['quantity'] - $alreadyReturned, 3);
            if ($quantity > $availableToReturn) {
                throw new RuntimeException('Quantidade devolvida maior que a quantidade disponivel para devolucao.');
            }

            $timestamp = dbNow();
            $productStmt = $this->pdo->prepare('SELECT quantity FROM products WHERE id = ?' . selectForUpdateClause());
            $productStmt->execute([$item['product_id']]);
            $currentQty = (float) $productStmt->fetchColumn();
            $newQty = round($currentQty + $quantity, 3);
            $refundAmount = round(((float) $item['line_total'] / max(0.001, (float) $item['quantity'])) * $quantity, 2);

            $this->pdo->prepare('UPDATE products SET quantity = ? WHERE id = ?')->execute([$newQty, $item['product_id']]);
            $this->pdo->prepare("
                INSERT INTO stock_movements
                    (product_id, user_id, type, quantity, old_quantity, new_quantity, sale_id, sale_item_id, reason, date)
                VALUES (?, ?, 'return', ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $item['product_id'],
                $_SESSION['user_id'],
                $quantity,
                $currentQty,
                $newQty,
                $saleId,
                $itemId,
                'Devolucao parcial: ' . $reason,
                $timestamp,
            ]);

            if ($refundMethod === 'cash_refund') {
                $cash = $this->cashRegisterForRefund((int) $sale['cash_register_id']);
                $this->pdo->prepare("
                    INSERT INTO cash_movements
                        (cash_register_id, sale_id, user_id, type, payment_method, amount, reason, created_at)
                    VALUES (?, ?, ?, 'refund', 'cash', ?, ?, ?)
                ")->execute([$cash['id'], $saleId, $_SESSION['user_id'], $refundAmount, 'Devolucao parcial: ' . $reason, $timestamp]);
                $this->pdo->prepare('UPDATE cash_registers SET expected_balance = expected_balance - ? WHERE id = ?')
                    ->execute([$refundAmount, $cash['id']]);
            } else {
                $customerId = (int) ($sale['customer_id'] ?? 0);
                if (!$this->isRealCustomerSale($sale)) {
                    throw new RuntimeException('Credito interno exige cliente cadastrado na venda.');
                }
                $this->pdo->prepare("
                    INSERT INTO customer_credits
                        (customer_id, sale_id, amount, used_amount, reason, status, created_by, created_at)
                    VALUES (?, ?, ?, 0, ?, 'open', ?, ?)
                ")->execute([$customerId, $saleId, $refundAmount, 'Devolucao parcial: ' . $reason, $_SESSION['user_id'], $timestamp]);
            }

            $this->pdo->prepare("
                INSERT INTO financial_ledger (source_table, source_id, entry_type, amount, description, created_by, created_at)
                VALUES ('sales', ?, 'adjustment', ?, ?, ?, ?)
            ")->execute([$saleId, -abs($refundAmount), 'Devolucao parcial', $_SESSION['user_id'], $timestamp]);

            $this->pdo->commit();
            Security::auditLog('sale_item_returned', [
                'module' => 'sales',
                'record_id' => (string) $saleId,
                'after' => ['item_id' => $itemId, 'quantity' => $quantity, 'refund_amount' => $refundAmount, 'refund_method' => $refundMethod],
            ]);
            setFlashMessage('success', 'Devolucao parcial registrada.');
            redirect('sales', ['action' => 'view', 'id' => $saleId]);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            setFlashMessage('error', $e->getMessage());
            redirect('sales', ['action' => 'view', 'id' => $saleId]);
        }
    }

    private function findSale(int $id): ?array {
        $stmt = $this->pdo->prepare("
            SELECT s.*, c.name AS customer_name, c.is_default AS customer_is_default, u.name AS user_name
            FROM sales s
            LEFT JOIN customers c ON c.id = s.customer_id
            LEFT JOIN users u ON u.id = s.user_id
            WHERE s.id = ?
        ");
        $stmt->execute([$id]);
        $sale = $stmt->fetch();
        return $sale ?: null;
    }

    private function isRealCustomerSale(array $sale): bool {
        return (int) ($sale['customer_id'] ?? 0) > 0
            && (int) ($sale['customer_is_default'] ?? 0) === 0;
    }

    private function cashRegisterForRefund(int $originalCashRegisterId): array {
        $stmt = $this->pdo->prepare('SELECT id, status FROM cash_registers WHERE id = ? LIMIT 1');
        $stmt->execute([$originalCashRegisterId]);
        $original = $stmt->fetch();
        if ($original && $original['status'] === 'open') {
            return $original;
        }

        $stmt = $this->pdo->prepare("SELECT id, status FROM cash_registers WHERE user_id = ? AND status = 'open' ORDER BY opened_at DESC LIMIT 1");
        $stmt->execute([$_SESSION['user_id'] ?? 0]);
        $current = $stmt->fetch();
        if ($current) {
            return $current;
        }

        throw new RuntimeException('Abra um caixa atual para registrar o estorno financeiro.');
    }

    private function renderSaleDocument(array $sale, array $items, array $payments): string {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <title>Venda <?php echo htmlspecialchars($sale['sale_number']); ?></title>
            <style>
                body { font-family: Arial, sans-serif; color: #1f2937; font-size: 12px; }
                h1 { font-size: 20px; margin-bottom: 4px; }
                .meta, .notice { color: #6b7280; margin-bottom: 14px; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                th, td { border: 1px solid #d1d5db; padding: 6px; text-align: left; }
                th { background: #f3f4f6; }
                .totals { margin-top: 14px; text-align: right; }
            </style>
        </head>
        <body>
            <h1><?php echo htmlspecialchars(APP_NAME); ?> - Venda <?php echo htmlspecialchars($sale['sale_number']); ?></h1>
            <div class="meta">
                <?php echo htmlspecialchars(COMPANY_LEGAL_NAME); ?> - CNPJ <?php echo htmlspecialchars(COMPANY_CNPJ); ?><br>
                <?php echo htmlspecialchars(COMPANY_ADDRESS); ?><br>
                Cliente: <?php echo htmlspecialchars($sale['customer_name'] ?? 'Consumidor Final'); ?> |
                Operador: <?php echo htmlspecialchars($sale['user_name'] ?? '-'); ?> |
                Data: <?php echo htmlspecialchars(formatDate($sale['created_at'])); ?>
            </div>
            <table>
                <thead><tr><th>Produto</th><th>Qtd.</th><th>Unitario</th><th>Desc.</th><th>Total</th></tr></thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td><?php echo htmlspecialchars(formatQuantity($item['quantity'])); ?></td>
                            <td><?php echo htmlspecialchars(formatMoney((float) $item['unit_price'])); ?></td>
                            <td><?php echo htmlspecialchars(formatMoney((float) $item['discount_amount'])); ?></td>
                            <td><?php echo htmlspecialchars(formatMoney((float) $item['line_total'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="totals">
                Subtotal: <?php echo htmlspecialchars(formatMoney((float) $sale['subtotal'])); ?><br>
                Desconto: <?php echo htmlspecialchars(formatMoney((float) $sale['discount_amount'])); ?><br>
                <strong>Total: <?php echo htmlspecialchars(formatMoney((float) $sale['total_amount'])); ?></strong>
            </div>
            <h2>Pagamentos</h2>
            <table>
                <thead><tr><th>Forma</th><th>Valor</th><th>Parcelas</th><th>Troco</th></tr></thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(PAYMENT_METHODS[$payment['payment_method']] ?? $payment['payment_method']); ?></td>
                            <td><?php echo htmlspecialchars(formatMoney((float) $payment['amount'])); ?></td>
                            <td><?php echo (int) $payment['installments']; ?></td>
                            <td><?php echo htmlspecialchars(formatMoney((float) $payment['change_amount'])); ?></td>
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
