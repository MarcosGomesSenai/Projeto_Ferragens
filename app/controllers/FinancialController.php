<?php
/**
 * Financeiro basico: contas a pagar e contas a receber.
 */

class FinancialController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function index(): void {
        $this->payables();
    }

    public function payables(): void {
        Security::checkPermissions('manager');
        $payables = $this->loadPayables(); // B-03: centralizado em loadPayables()
        $receivables = $this->loadReceivables();
        require_once APP_PATH . '/views/financial/index.php';
    }

    public function receivables(): void {
        Security::checkPermissions('manager');
        $payables = $this->loadPayables();
        $receivables = $this->loadReceivables();
        require_once APP_PATH . '/views/financial/index.php';
    }

    public function payPayable(): void {
        Security::checkPermissions('manager');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('financial');
        }
        Security::validateRequest();

        $id = (int) ($_POST['id'] ?? 0);
        $amount = normalizeMoney($_POST['paid_amount'] ?? 0);
        $method = $_POST['payment_method'] ?? '';
        if (!in_array($method, ['pix', 'transfer', 'boleto', 'check', 'cash'], true) || $amount <= 0) {
            setFlashMessage('error', 'Pagamento invalido.');
            redirect('financial');
        }

        $stmt = $this->pdo->prepare('SELECT * FROM accounts_payable WHERE id = ?');
        $stmt->execute([$id]);
        $payable = $stmt->fetch();
        if (!$payable) {
            setFlashMessage('error', 'Conta nao encontrada.');
            redirect('financial');
        }
        if ($payable['status'] === 'paid') {
            setFlashMessage('warning', 'Conta ja esta paga.');
            redirect('financial');
        }

        $remaining = round((float) $payable['amount'] - (float) $payable['paid_amount'], 2);
        $rawNewPaid = round((float) $payable['paid_amount'] + $amount, 2);
        $extraAmount = max(0.0, round($rawNewPaid - (float) $payable['amount'], 2));
        $newPaid = min((float) $payable['amount'], $rawNewPaid);
        $status = $newPaid >= (float) $payable['amount'] ? 'paid' : 'partial';
        $notes = (string) ($payable['notes'] ?? '');
        if ($extraAmount > 0) {
            $notes = trim($notes . ' Acrescimo no pagamento: ' . formatMoney($extraAmount) . '.');
        }

        $this->pdo->beginTransaction();
        try {
            $paymentDate = dbToday();
            $this->pdo->prepare("
                UPDATE accounts_payable
                SET paid_amount = ?, payment_date = ?, payment_method = ?, status = ?, notes = ?
                WHERE id = ?
            ")->execute([$newPaid, $paymentDate, $method, $status, $notes, $id]);

            $this->pdo->prepare("
                INSERT INTO financial_ledger (source_table, source_id, entry_type, amount, description, created_by, created_at)
                VALUES ('accounts_payable', ?, 'expense', ?, ?, ?, ?)
            ")->execute([$id, $amount, 'Pagamento de conta a pagar', $_SESSION['user_id'], dbNow()]);

            if ($method === 'cash') {
                $cash = $this->currentOpenCash();
                if (!$cash) {
                    throw new RuntimeException('Abra o caixa antes de pagar uma conta em dinheiro.');
                }
                $this->pdo->prepare('UPDATE cash_registers SET expected_balance = expected_balance - ? WHERE id = ?')
                    ->execute([$amount, $cash['id']]);
                $this->pdo->prepare("
                    INSERT INTO cash_movements (cash_register_id, user_id, type, payment_method, amount, reason, created_at)
                    VALUES (?, ?, 'withdrawal', 'cash', ?, ?, ?)
                ")->execute([$cash['id'], $_SESSION['user_id'], $amount, 'Pagamento de conta a pagar #' . $id, dbNow()]);
            }

            $this->pdo->commit();
            Security::auditLog('payable_payment_registered', [
                'module' => 'financial',
                'record_id' => (string) $id,
                'after' => ['paid_amount' => $amount, 'remaining_before' => $remaining, 'extra_amount' => $extraAmount, 'status' => $status, 'method' => $method],
            ]);
            setFlashMessage('success', 'Pagamento registrado com sucesso.');
            redirect('financial');
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            setFlashMessage('error', $e instanceof RuntimeException ? $e->getMessage() : 'Nao foi possivel registrar o pagamento.');
            redirect('financial');
        }
    }

    public function receiveReceivable(): void {
        Security::checkPermissions('manager');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('financial');
        }
        Security::validateRequest();

        $id = (int) ($_POST['id'] ?? 0);
        $amount = normalizeMoney($_POST['received_amount'] ?? 0);
        $method = $_POST['payment_method'] ?? '';
        if (!in_array($method, ['pix', 'transfer', 'cash'], true) || $amount <= 0) {
            setFlashMessage('error', 'Recebimento invalido.');
            redirect('financial', ['action' => 'receivables']);
        }

        $stmt = $this->pdo->prepare('SELECT * FROM accounts_receivable WHERE id = ?');
        $stmt->execute([$id]);
        $receivable = $stmt->fetch();
        if (!$receivable) {
            setFlashMessage('error', 'Conta a receber nao encontrada.');
            redirect('financial', ['action' => 'receivables']);
        }
        if ($receivable['source'] !== 'store_credit') {
            setFlashMessage('error', 'Recebimento manual e permitido apenas para crediario da loja.');
            redirect('financial', ['action' => 'receivables']);
        }
        if ($receivable['status'] === 'paid') {
            setFlashMessage('warning', 'Parcela ja esta paga.');
            redirect('financial', ['action' => 'receivables']);
        }

        $remaining = round((float) $receivable['amount'] - (float) $receivable['received_amount'], 2);
        if ($amount > $remaining) {
            setFlashMessage('error', 'Valor recebido maior que o saldo da parcela.');
            redirect('financial', ['action' => 'receivables']);
        }
        $newReceived = round((float) $receivable['received_amount'] + $amount, 2);
        $status = $newReceived >= (float) $receivable['amount'] ? 'paid' : 'partial';

        $this->pdo->beginTransaction();
        try {
            $timestamp = dbNow();
            $this->pdo->prepare("
                UPDATE accounts_receivable
                SET received_amount = ?, received_date = ?, status = ?, updated_at = ?
                WHERE id = ?
            ")->execute([$newReceived, dbToday(), $status, $timestamp, $id]);

            $this->pdo->prepare("
                INSERT INTO financial_ledger (source_table, source_id, entry_type, amount, description, created_by, created_at)
                VALUES ('accounts_receivable', ?, 'income', ?, ?, ?, ?)
            ")->execute([$id, $amount, 'Recebimento de crediario via ' . $method, $_SESSION['user_id'], $timestamp]);

            if ($method === 'cash') {
                $cash = $this->currentOpenCash();
                if (!$cash) {
                    throw new RuntimeException('Abra o caixa antes de receber crediario em dinheiro.');
                }
                $this->pdo->prepare('UPDATE cash_registers SET expected_balance = expected_balance + ? WHERE id = ?')
                    ->execute([$amount, $cash['id']]);
                $this->pdo->prepare("
                    INSERT INTO cash_movements (cash_register_id, user_id, type, payment_method, amount, reason, created_at)
                    VALUES (?, ?, 'supply', 'cash', ?, ?, ?)
                ")->execute([$cash['id'], $_SESSION['user_id'], $amount, 'Recebimento de crediario #' . $id, $timestamp]);
            }

            $this->pdo->commit();
            Security::auditLog('receivable_payment_registered', [
                'module' => 'financial',
                'record_id' => (string) $id,
                'after' => ['received_amount' => $amount, 'status' => $status, 'method' => $method],
            ]);
            setFlashMessage('success', 'Recebimento registrado com sucesso.');
            redirect('financial', ['action' => 'receivables']);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            setFlashMessage('error', $e instanceof RuntimeException ? $e->getMessage() : 'Nao foi possivel registrar o recebimento.');
            redirect('financial', ['action' => 'receivables']);
        }
    }

    private function loadPayables(): array {
        return $this->pdo->query("
            SELECT ap.*, s.name AS supplier_name
            FROM accounts_payable ap
            INNER JOIN suppliers s ON s.id = ap.supplier_id
            ORDER BY ap.due_date ASC, ap.id DESC
            LIMIT 200
        ")->fetchAll();
    }

    private function loadReceivables(): array {
        return $this->pdo->query("
            SELECT ar.*, c.name AS customer_name
            FROM accounts_receivable ar
            LEFT JOIN customers c ON c.id = ar.customer_id
            ORDER BY ar.due_date ASC, ar.id DESC
            LIMIT 200
        ")->fetchAll();
    }

    private function currentOpenCash(): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM cash_registers WHERE user_id = ? AND status = 'open' ORDER BY opened_at DESC LIMIT 1");
        $stmt->execute([$_SESSION['user_id'] ?? 0]);
        $cash = $stmt->fetch();
        return $cash ?: null;
    }
}
