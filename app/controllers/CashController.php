<?php
/**
 * Controle de caixa diario por operador.
 */

class CashController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function index(): void {
        Security::checkPermissions('manager');
        $currentCash = $this->currentOpenCash();
        $pendingRegisters = $this->pendingPreviousRegisters();
        $openRegisters = $this->pdo->query("
            SELECT cr.*, u.name AS user_name
            FROM cash_registers cr
            INNER JOIN users u ON u.id = cr.user_id
            WHERE cr.status = 'open'
            ORDER BY cr.opened_at DESC
        ")->fetchAll();
        $recentMovements = [];
        if ($currentCash) {
            $stmt = $this->pdo->prepare('SELECT * FROM cash_movements WHERE cash_register_id = ? ORDER BY created_at DESC LIMIT 20');
            $stmt->execute([$currentCash['id']]);
            $recentMovements = $stmt->fetchAll();
        }
        require_once APP_PATH . '/views/cash/index.php';
    }

    public function open(): void {
        // Vendedores podem abrir o proprio caixa para operar o PDV.
        // A tela gerencial de caixa continua restrita a manager/admin.
        Security::checkPermissions('seller');
        $returnPage = hasPermission('manager') ? 'cash' : 'pos';
        if ($this->pendingPreviousRegisters()) {
            setFlashMessage('error', 'Existe caixa anterior pendente de fechamento. Solicite fechamento retroativo ao administrador.');
            redirect($returnPage);
        }
        if ($this->currentOpenCash()) {
            setFlashMessage('warning', 'Voce ja possui um caixa aberto.');
            redirect($returnPage);
        }
        require_once APP_PATH . '/views/cash/open.php';
    }

    public function saveOpen(): void {
        Security::checkPermissions('seller');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('cash', ['action' => 'open']);
        }
        Security::validateRequest();

        $returnPage = hasPermission('manager') ? 'cash' : 'pos';
        if ($this->pendingPreviousRegisters()) {
            setFlashMessage('error', 'Existe caixa anterior pendente de fechamento. Solicite fechamento retroativo ao administrador.');
            redirect($returnPage);
        }

        if ($this->currentOpenCash()) {
            setFlashMessage('error', 'Ja existe caixa aberto para seu usuario.');
            redirect($returnPage);
        }

        $initial = normalizeMoney($_POST['initial_balance'] ?? 0);
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO cash_registers (user_id, initial_balance, expected_balance, status)
                VALUES (?, ?, ?, 'open')
            ");
            $stmt->execute([$_SESSION['user_id'], $initial, $initial]);
            $cashId = (int) $this->pdo->lastInsertId();

            $stmt = $this->pdo->prepare("
                INSERT INTO cash_movements (cash_register_id, user_id, type, payment_method, amount, reason)
                VALUES (?, ?, 'opening', 'cash', ?, 'Abertura de caixa')
            ");
            $stmt->execute([$cashId, $_SESSION['user_id'], $initial]);

            $this->pdo->commit();
            Security::auditLog('cash_opened', ['module' => 'cash', 'record_id' => (string) $cashId, 'after' => ['initial_balance' => $initial]]);
            setFlashMessage('success', 'Caixa aberto com sucesso.');
            redirect('pos');
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            error_log('[Ferragens Souza] Erro ao abrir caixa: ' . $e->getMessage());
            setFlashMessage('error', 'Nao foi possivel abrir o caixa.');
            redirect('cash', ['action' => 'open']);
        }
    }

    public function movement(): void {
        Security::checkPermissions('manager');
        $currentCash = $this->currentOpenCash();
        if (!$currentCash) {
            setFlashMessage('error', 'Abra o caixa antes de registrar movimento.');
            redirect('cash', ['action' => 'open']);
        }
        require_once APP_PATH . '/views/cash/movement.php';
    }

    public function saveMovement(): void {
        Security::checkPermissions('manager');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('cash');
        }
        Security::validateRequest();

        $currentCash = $this->currentOpenCash();
        if (!$currentCash) {
            setFlashMessage('error', 'Nenhum caixa aberto.');
            redirect('cash', ['action' => 'open']);
        }

        $type = $_POST['type'] ?? '';
        if (!in_array($type, ['withdrawal', 'supply'], true)) {
            setFlashMessage('error', 'Tipo de movimento invalido.');
            redirect('cash', ['action' => 'movement']);
        }

        $amount = normalizeMoney($_POST['amount'] ?? 0);
        $reason = sanitize($_POST['reason'] ?? '');
        if ($amount <= 0 || $reason === '') {
            setFlashMessage('error', 'Informe valor e motivo.');
            redirect('cash', ['action' => 'movement']);
        }
        // saveMovement() ja e restrito a manager/admin. Logo, sangria feita aqui
        // fica automaticamente aprovada pelo proprio usuario autenticado.
        $approvedBy = $type === 'withdrawal' ? (int) $_SESSION['user_id'] : null;

        $delta = $type === 'withdrawal' ? -$amount : $amount;
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('UPDATE cash_registers SET expected_balance = expected_balance + ? WHERE id = ?')
                ->execute([$delta, $currentCash['id']]);
            $this->pdo->prepare("
                INSERT INTO cash_movements (cash_register_id, user_id, type, payment_method, amount, reason, approved_by)
                VALUES (?, ?, ?, 'cash', ?, ?, ?)
            ")->execute([
                $currentCash['id'],
                $_SESSION['user_id'],
                $type,
                $amount,
                $reason,
                $approvedBy,
            ]);
            $this->pdo->commit();
            Security::auditLog('cash_movement_created', ['module' => 'cash', 'record_id' => (string) $currentCash['id'], 'after' => compact('type', 'amount', 'reason')]);
            setFlashMessage('success', 'Movimento registrado.');
            redirect('cash');
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            setFlashMessage('error', 'Nao foi possivel registrar o movimento.');
            redirect('cash', ['action' => 'movement']);
        }
    }

    public function close(): void {
        Security::checkPermissions('manager');
        $currentCash = $this->currentOpenCash();
        if (!$currentCash) {
            setFlashMessage('error', 'Nenhum caixa aberto.');
            redirect('cash');
        }
        require_once APP_PATH . '/views/cash/close.php';
    }

    public function saveClose(): void {
        Security::checkPermissions('manager');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('cash');
        }
        Security::validateRequest();

        $currentCash = $this->currentOpenCash();
        if (!$currentCash) {
            setFlashMessage('error', 'Nenhum caixa aberto.');
            redirect('cash');
        }

        $counted = normalizeMoney($_POST['counted_balance'] ?? 0);
        $expected = (float) $currentCash['expected_balance'];
        $difference = $counted - $expected;
        $adminApprovalId = hasPermission('admin') && $difference < -CASH_SHORTAGE_ADMIN_LIMIT
            ? (int) $_SESSION['user_id']
            : null;

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                UPDATE cash_registers
                SET counted_balance = ?, difference_amount = ?, closed_at = ?, closed_by = ?,
                    admin_approval_id = ?, close_notes = ?, status = 'closed'
                WHERE id = ?
            ");
            $stmt->execute([$counted, $difference, dbNow(), $_SESSION['user_id'], $adminApprovalId, sanitize($_POST['close_notes'] ?? ''), $currentCash['id']]);
            $this->pdo->commit();

            Security::auditLog('cash_closed', [
                'module' => 'cash',
                'record_id' => (string) $currentCash['id'],
                'after' => ['expected' => $expected, 'counted' => $counted, 'difference' => $difference, 'admin_approval_id' => $adminApprovalId],
            ]);
            setFlashMessage('success', 'Caixa fechado com sucesso.');
            redirect('cash');
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            setFlashMessage('error', 'Nao foi possivel fechar o caixa.');
            redirect('cash', ['action' => 'close']);
        }
    }

    public function forceClose(): void {
        Security::checkPermissions('admin');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('cash');
        }
        Security::validateRequest();

        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $this->pdo->prepare("SELECT * FROM cash_registers WHERE id = ? AND status = 'open' LIMIT 1");
        $stmt->execute([$id]);
        $cash = $stmt->fetch();
        if (!$cash) {
            setFlashMessage('error', 'Caixa pendente nao encontrado.');
            redirect('cash');
        }

        $this->pdo->prepare("
            UPDATE cash_registers
            SET counted_balance = expected_balance, difference_amount = 0, closed_at = ?, closed_by = ?,
                close_notes = ?, status = 'forced_closed'
            WHERE id = ?
        ")->execute([dbNow(), $_SESSION['user_id'], 'Fechamento retroativo forcado pelo administrador', $id]);

        Security::auditLog('cash_forced_closed', [
            'module' => 'cash',
            'record_id' => (string) $id,
            'before' => $cash,
            'after' => ['status' => 'forced_closed'],
        ]);
        setFlashMessage('success', 'Caixa fechado retroativamente.');
        redirect('cash');
    }

    private function currentOpenCash(): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM cash_registers WHERE user_id = ? AND status = 'open' ORDER BY opened_at DESC LIMIT 1");
        $stmt->execute([$_SESSION['user_id'] ?? 0]);
        $cash = $stmt->fetch();
        return $cash ?: null;
    }

    private function pendingPreviousRegisters(): array {
        $stmt = $this->pdo->prepare("
            SELECT cr.*, u.name AS user_name
            FROM cash_registers cr
            INNER JOIN users u ON u.id = cr.user_id
            WHERE cr.status = 'open'
              AND DATE(cr.opened_at) < ?
            ORDER BY cr.opened_at ASC
        ");
        $stmt->execute([dbToday()]);
        return $stmt->fetchAll();
    }
}
