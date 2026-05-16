<?php
/**
 * Orcamentos basicos com validade configuravel.
 */

class QuotationController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function index(): void {
        Security::checkPermissions('seller');
        $this->expireOldQuotations();
        $quotations = $this->pdo->query("
            SELECT q.*, c.name AS customer_name_db, u.name AS user_name
            FROM quotations q
            LEFT JOIN customers c ON c.id = q.customer_id
            LEFT JOIN users u ON u.id = q.user_id
            ORDER BY q.created_at DESC
            LIMIT 100
        ")->fetchAll();
        require_once APP_PATH . '/views/quotations/list.php';
    }

    public function add(): void {
        Security::checkPermissions('seller');
        $customers = $this->pdo->query("
            SELECT id, name, customer_type
            FROM customers
            WHERE status = 'active'
            ORDER BY is_default DESC, name ASC
        ")->fetchAll();
        require_once APP_PATH . '/views/quotations/add.php';
    }

    public function save(): void {
        Security::checkPermissions('seller');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('quotations', ['action' => 'add']);
        }
        Security::validateRequest();

        $items = json_decode($_POST['items_json'] ?? '[]', true);
        if (!is_array($items) || !$items) {
            setFlashMessage('error', 'Adicione itens ao orcamento.');
            redirect('quotations', ['action' => 'add']);
        }

        $customerId = (int) ($_POST['customer_id'] ?? 1);
        $customerName = sanitize($_POST['customer_name'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');
        $overallDiscount = normalizeMoney($_POST['overall_discount'] ?? 0);
        $customer = $this->findCustomer($customerId);
        $approvalEmail = strtolower(trim(filter_var($_POST['approval_email'] ?? '', FILTER_SANITIZE_EMAIL)));
        $approvalPassword = $_POST['approval_password'] ?? '';
        $confirmBelowCost = ($_POST['confirm_below_cost'] ?? '') === '1';

        $validatedItems = [];
        $subtotal = 0.0;
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = normalizeQuantity($item['quantity'] ?? 0);
            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }
            $stmt = $this->pdo->prepare("
                SELECT id, sku, name, cost_price, sale_price, wholesale_price, status
                FROM products
                WHERE id = ?
                  AND (status = 'active' OR (status = 'discontinued' AND quantity > 0))
            ");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            if (!$product) {
                continue;
            }
            $basePrice = $customer && $customer['customer_type'] === 'professional' && !empty($product['wholesale_price'])
                ? (float) $product['wholesale_price']
                : (float) $product['sale_price'];
            $unitPrice = round($basePrice, 2);
            $lineTotal = round($unitPrice * $quantity, 2);
            $validatedItems[] = [
                'product' => $product,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ];
            $subtotal += $lineTotal;
        }

        if (!$validatedItems) {
            setFlashMessage('error', 'Nenhum item valido no orcamento.');
            redirect('quotations', ['action' => 'add']);
        }

        if ($overallDiscount < 0 || $overallDiscount > $subtotal) {
            setFlashMessage('error', 'Desconto total invalido.');
            redirect('quotations', ['action' => 'add']);
        }
        $effectiveDiscountPercent = $subtotal > 0 ? ($overallDiscount / $subtotal) * 100 : 0;
        try {
            $this->assertDiscountAuthority($effectiveDiscountPercent, $approvalEmail, $approvalPassword);
            // B-01: correção de rounding drift — último item absorve a diferença residual
            $baseForShare = max(0.01, $subtotal);
            $lastItemKey = array_key_last($validatedItems);
            $allocatedShareSave = 0.0;
            foreach ($validatedItems as $saveItemKey => $validatedItem) {
                $share = $overallDiscount > 0
                    ? ($saveItemKey === $lastItemKey
                        ? round($overallDiscount - $allocatedShareSave, 2)
                        : round($overallDiscount * ($validatedItem['line_total'] / $baseForShare), 2))
                    : 0.0;
                $allocatedShareSave += $share;
                $finalLineTotal = round($validatedItem['line_total'] - $share, 2);
                $minimumCost = round((float) $validatedItem['product']['cost_price'] * $validatedItem['quantity'], 2);
                if ($finalLineTotal < $minimumCost) {
                    if (!$this->hasAuthority('admin', $approvalEmail, $approvalPassword)) {
                        throw new RuntimeException('Desconto venderia item abaixo do custo.');
                    }
                    if (!$confirmBelowCost) {
                        throw new RuntimeException('Orcamento abaixo do custo exige confirmacao explicita.');
                    }
                }
            }
        } catch (Throwable $e) {
            setFlashMessage('error', $e->getMessage());
            redirect('quotations', ['action' => 'add']);
        }

        $total = round($subtotal - $overallDiscount, 2);
        $validUntil = addBusinessDays(new DateTimeImmutable('today'), DEFAULT_QUOTATION_VALID_BUSINESS_DAYS)->format('Y-m-d');
        $quotationNumber = 'OR-' . date('Ymd-His') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO quotations
                    (quotation_number, customer_id, customer_name, user_id, valid_until,
                     subtotal, discount_amount, total_amount, status, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?)
            ");
            $stmt->execute([
                $quotationNumber,
                $customer['id'] ?? null,
                $customerName !== '' ? $customerName : ($customer['name'] ?? 'Cliente nao identificado'),
                $_SESSION['user_id'],
                $validUntil,
                $subtotal,
                $overallDiscount,
                $total,
                $notes,
                dbNow(),
            ]);
            $quotationId = (int) $this->pdo->lastInsertId();

            foreach ($validatedItems as $item) {
                $product = $item['product'];
                $this->pdo->prepare("
                    INSERT INTO quotation_items
                        (quotation_id, product_id, sku_snapshot, product_name, quantity, unit_price, cost_price, line_total)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $quotationId,
                    $product['id'],
                    $product['sku'],
                    $product['name'],
                    $item['quantity'],
                    $item['unit_price'],
                    $product['cost_price'],
                    $item['line_total'],
                ]);
            }

            $this->pdo->commit();
            Security::auditLog('quotation_created', [
                'module' => 'quotations',
                'record_id' => (string) $quotationId,
                'after' => ['quotation_number' => $quotationNumber, 'total' => $total],
            ]);
            setFlashMessage('success', 'Orcamento criado com sucesso.');
            redirect('quotations', ['action' => 'view', 'id' => $quotationId]);
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            setFlashMessage('error', 'Nao foi possivel criar o orcamento.');
            redirect('quotations', ['action' => 'add']);
        }
    }

    public function view(): void {
        Security::checkPermissions('seller');
        $id = (int) ($_GET['id'] ?? 0);
        $this->expireOldQuotations();
        $stmt = $this->pdo->prepare("
            SELECT q.*, c.name AS customer_name_db, u.name AS user_name
            FROM quotations q
            LEFT JOIN customers c ON c.id = q.customer_id
            LEFT JOIN users u ON u.id = q.user_id
            WHERE q.id = ?
        ");
        $stmt->execute([$id]);
        $quotation = $stmt->fetch();
        if (!$quotation) {
            setFlashMessage('error', 'Orcamento nao encontrado.');
            redirect('quotations');
        }

        $itemsStmt = $this->pdo->prepare('SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY id ASC');
        $itemsStmt->execute([$id]);
        $quotationItems = $itemsStmt->fetchAll();

        require_once APP_PATH . '/views/quotations/view.php';
    }

    public function pdf(): void {
        Security::checkPermissions('seller');
        $id = (int) ($_GET['id'] ?? 0);
        $quotation = $this->findQuotationWithNames($id);
        if (!$quotation) {
            setFlashMessage('error', 'Orcamento nao encontrado.');
            redirect('quotations');
        }
        $quotationItems = $this->quotationItems($id);
        $html = $this->renderQuotationDocument($quotation, $quotationItems);
        $this->outputPdf('orcamento_' . preg_replace('/[^A-Za-z0-9_-]/', '', $quotation['quotation_number']) . '.pdf', $html);
    }

    public function status(): void {
        Security::checkPermissions('seller');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('quotations');
        }
        Security::validateRequest();

        $id = (int) ($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['sent', 'approved', 'rejected'], true)) {
            setFlashMessage('error', 'Status de orcamento invalido.');
            redirect('quotations', ['action' => 'view', 'id' => $id]);
        }

        $quotation = $this->findQuotation($id);
        if (!$quotation || in_array($quotation['status'], ['converted', 'expired'], true)) {
            setFlashMessage('error', 'Orcamento nao pode mudar de status.');
            redirect('quotations');
        }

        $this->pdo->prepare('UPDATE quotations SET status = ?, updated_at = ? WHERE id = ?')
            ->execute([$status, dbNow(), $id]);
        Security::auditLog('quotation_status_changed', [
            'module' => 'quotations',
            'record_id' => (string) $id,
            'before' => ['status' => $quotation['status']],
            'after' => ['status' => $status],
        ]);
        setFlashMessage('success', 'Status do orcamento atualizado.');
        redirect('quotations', ['action' => 'view', 'id' => $id]);
    }

    public function reopen(): void {
        Security::checkPermissions('seller');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('quotations');
        }
        Security::validateRequest();

        $id = (int) ($_POST['id'] ?? 0);
        $quotation = $this->findQuotation($id);
        if (!$quotation || $quotation['status'] === 'converted') {
            setFlashMessage('error', 'Orcamento nao pode ser reaberto.');
            redirect('quotations');
        }

        $validUntil = addBusinessDays(new DateTimeImmutable('today'), DEFAULT_QUOTATION_VALID_BUSINESS_DAYS)->format('Y-m-d');
        $this->pdo->prepare("UPDATE quotations SET status = 'draft', valid_until = ?, updated_at = ? WHERE id = ?")
            ->execute([$validUntil, dbNow(), $id]);
        Security::auditLog('quotation_reopened', [
            'module' => 'quotations',
            'record_id' => (string) $id,
            'after' => ['status' => 'draft', 'valid_until' => $validUntil],
        ]);
        setFlashMessage('success', 'Orcamento reaberto com nova validade.');
        redirect('quotations', ['action' => 'view', 'id' => $id]);
    }

    public function convert(): void {
        Security::checkPermissions('seller');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('quotations');
        }
        Security::validateRequest();

        $id = (int) ($_POST['id'] ?? 0);
        $quotation = $this->findQuotation($id);
        if (!$quotation) {
            setFlashMessage('error', 'Orcamento nao encontrado.');
            redirect('quotations');
        }
        if ($quotation['status'] === 'converted') {
            setFlashMessage('warning', 'Orcamento ja convertido em venda.');
            redirect('quotations', ['action' => 'view', 'id' => $id]);
        }
        if ($this->isExpired($quotation)) {
            $this->markExpired($id);
            setFlashMessage('error', 'Orcamento expirado. Reabra antes de converter.');
            redirect('quotations', ['action' => 'view', 'id' => $id]);
        }
        if ($quotation['status'] !== 'approved') {
            setFlashMessage('error', 'Aprove o orcamento antes de converter em venda.');
            redirect('quotations', ['action' => 'view', 'id' => $id]);
        }

        $cashRegister = $this->currentOpenCash();
        if (!$cashRegister) {
            setFlashMessage('error', 'Abra o caixa antes de converter o orcamento em venda.');
            redirect('quotations', ['action' => 'view', 'id' => $id]);
        }

        $items = $this->quotationItems($id);
        if (!$items) {
            setFlashMessage('error', 'Orcamento sem itens.');
            redirect('quotations', ['action' => 'view', 'id' => $id]);
        }

        try {
            $payment = $this->paymentFromPost((float) $quotation['total_amount'], (int) ($quotation['customer_id'] ?? 1));
        } catch (Throwable $e) {
            setFlashMessage('error', $e->getMessage());
            redirect('quotations', ['action' => 'view', 'id' => $id]);
        }
        $confirmBelowCost = ($_POST['confirm_below_cost'] ?? '') === '1';
        $confirmNegativeStock = ($_POST['confirm_negative_stock'] ?? '') === '1';
        $approvalEmail = strtolower(trim(filter_var($_POST['approval_email'] ?? '', FILTER_SANITIZE_EMAIL)));
        $approvalPassword = $_POST['approval_password'] ?? '';
        $timestamp = dbNow();

        $this->pdo->beginTransaction();
        try {
            $saleNumber = $this->generateSaleNumber();
            $this->pdo->prepare("
                INSERT INTO sales
                    (sale_number, customer_id, cash_register_id, user_id, subtotal, discount_amount, total_amount, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?)
            ")->execute([
                $saleNumber,
                $quotation['customer_id'] ?: 1,
                $cashRegister['id'],
                $_SESSION['user_id'],
                $quotation['subtotal'],
                $quotation['discount_amount'],
                $quotation['total_amount'],
                $timestamp,
            ]);
            $saleId = (int) $this->pdo->lastInsertId();

            $overallDiscount = (float) $quotation['discount_amount'];
            $baseForDiscount = max(0.01, array_sum(array_map(static fn($row) => (float) $row['line_total'], $items)));
            $allocatedDiscount = 0.0;
            $lastItemIndex = array_key_last($items);
            foreach ($items as $itemIndex => $item) {
                $product = $this->lockProduct((int) $item['product_id']);
                if (!$product || !in_array($product['status'], ['active', 'discontinued'], true)) {
                    throw new RuntimeException('Produto indisponivel: ' . $item['product_name']);
                }
                if ((float) $product['quantity'] < (float) $item['quantity']) {
                    if (!$this->hasAuthority('admin', $approvalEmail, $approvalPassword) || !$confirmNegativeStock) {
                        throw new RuntimeException('Estoque insuficiente para ' . $item['product_name'] . '. Conversao sem estoque exige autorizacao de administrador.');
                    }
                }
                $discountShare = $overallDiscount > 0
                    ? ($itemIndex === $lastItemIndex
                        ? round($overallDiscount - $allocatedDiscount, 2)
                        : round($overallDiscount * ((float) $item['line_total'] / $baseForDiscount), 2))
                    : 0.0;
                $allocatedDiscount += $discountShare;
                $finalLineTotal = round((float) $item['line_total'] - $discountShare, 2);

                if ($finalLineTotal < ((float) $item['cost_price'] * (float) $item['quantity'])) {
                    if (!$this->hasAuthority('admin', $approvalEmail, $approvalPassword)) {
                        throw new RuntimeException('Orcamento vende item abaixo do custo: ' . $item['product_name']);
                    }
                    if (!$confirmBelowCost) {
                        throw new RuntimeException('Conversao abaixo do custo exige confirmacao explicita.');
                    }
                }

                $newQuantity = round((float) $product['quantity'] - (float) $item['quantity'], 3);
                $saleItemStmt = $this->pdo->prepare("
                    INSERT INTO sale_items
                        (sale_id, product_id, sku_snapshot, product_name, quantity, unit_price,
                         cost_price, discount_percent, discount_amount, line_total)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?)
                ");
                $saleItemStmt->execute([
                    $saleId,
                    $item['product_id'],
                    $item['sku_snapshot'],
                    $item['product_name'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['cost_price'],
                    $discountShare,
                    $finalLineTotal,
                ]);
                $saleItemId = (int) $this->pdo->lastInsertId();
                $this->pdo->prepare('UPDATE products SET quantity = ? WHERE id = ?')->execute([$newQuantity, $item['product_id']]);
                $this->pdo->prepare("
                    INSERT INTO stock_movements
                        (product_id, user_id, type, quantity, old_quantity, new_quantity, sale_id, sale_item_id, reason, date)
                    VALUES (?, ?, 'exit', ?, ?, ?, ?, ?, 'Conversao de orcamento', ?)
                ")->execute([
                    $item['product_id'],
                    $_SESSION['user_id'],
                    $item['quantity'],
                    $product['quantity'],
                    $newQuantity,
                    $saleId,
                    $saleItemId,
                    $timestamp,
                ]);
            }

            $this->pdo->prepare("
                INSERT INTO sale_payments
                    (sale_id, payment_method, amount, installments, change_amount, confirmed, created_at)
                VALUES (?, ?, ?, ?, ?, 1, ?)
            ")->execute([
                $saleId,
                $payment['payment_method'],
                $payment['amount'],
                $payment['installments'],
                $payment['change_amount'],
                $timestamp,
            ]);
            $this->pdo->prepare("
                INSERT INTO cash_movements
                    (cash_register_id, sale_id, user_id, type, payment_method, amount, reason, created_at)
                VALUES (?, ?, ?, 'sale', ?, ?, 'Conversao de orcamento', ?)
            ")->execute([
                $cashRegister['id'],
                $saleId,
                $_SESSION['user_id'],
                $payment['payment_method'],
                $payment['net_amount'],
                $timestamp,
            ]);
            if ($payment['payment_method'] === 'cash') {
                $this->pdo->prepare('UPDATE cash_registers SET expected_balance = expected_balance + ? WHERE id = ?')
                    ->execute([$payment['net_amount'], $cashRegister['id']]);
            }
            if (in_array($payment['payment_method'], ['credit_card', 'store_credit'], true)) {
                $this->createReceivables($saleId, (int) ($quotation['customer_id'] ?: 1), $payment);
            }
            if ($payment['payment_method'] === 'customer_credit') {
                $this->consumeCustomerCredit((int) ($quotation['customer_id'] ?: 0), (float) $payment['net_amount'], $saleId);
            }

            $ledgerIncome = round((float) $quotation['total_amount'] - ($payment['payment_method'] === 'customer_credit' ? (float) $payment['net_amount'] : 0.0), 2);
            if ($ledgerIncome > 0) {
                $this->pdo->prepare("
                    INSERT INTO financial_ledger (source_table, source_id, entry_type, amount, description, created_by, created_at)
                    VALUES ('sales', ?, 'income', ?, ?, ?, ?)
                ")->execute([$saleId, $ledgerIncome, 'Venda de orcamento ' . $saleNumber, $_SESSION['user_id'], $timestamp]);
            }

            $this->pdo->prepare("UPDATE quotations SET status = 'converted', sale_id = ?, updated_at = ? WHERE id = ?")
                ->execute([$saleId, $timestamp, $id]);

            $this->pdo->commit();
            Security::auditLog('quotation_converted', [
                'module' => 'quotations',
                'record_id' => (string) $id,
                'after' => ['sale_id' => $saleId, 'sale_number' => $saleNumber],
            ]);
            setFlashMessage('success', 'Orcamento convertido em venda.');
            redirect('sales', ['action' => 'view', 'id' => $saleId]);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            setFlashMessage('error', $e->getMessage());
            redirect('quotations', ['action' => 'view', 'id' => $id]);
        }
    }

    private function findCustomer(int $customerId): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM customers WHERE id = ? AND status = "active"');
        $stmt->execute([$customerId]);
        $customer = $stmt->fetch();
        return $customer ?: null;
    }

    private function findQuotation(int $id): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM quotations WHERE id = ?');
        $stmt->execute([$id]);
        $quotation = $stmt->fetch();
        return $quotation ?: null;
    }

    private function findQuotationWithNames(int $id): ?array {
        $stmt = $this->pdo->prepare("
            SELECT q.*, c.name AS customer_name_db, u.name AS user_name
            FROM quotations q
            LEFT JOIN customers c ON c.id = q.customer_id
            LEFT JOIN users u ON u.id = q.user_id
            WHERE q.id = ?
        ");
        $stmt->execute([$id]);
        $quotation = $stmt->fetch();
        return $quotation ?: null;
    }

    private function quotationItems(int $quotationId): array {
        $stmt = $this->pdo->prepare('SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY id ASC');
        $stmt->execute([$quotationId]);
        return $stmt->fetchAll();
    }

    private function expireOldQuotations(): void {
        $this->pdo->prepare("
            UPDATE quotations
            SET status = 'expired', updated_at = ?
            WHERE status IN ('draft', 'sent', 'approved')
              AND valid_until < ?
        ")->execute([dbNow(), dbToday()]);
    }

    private function isExpired(array $quotation): bool {
        return strtotime((string) $quotation['valid_until']) < strtotime(dbToday());
    }

    private function markExpired(int $quotationId): void {
        $this->pdo->prepare("UPDATE quotations SET status = 'expired', updated_at = ? WHERE id = ?")
            ->execute([dbNow(), $quotationId]);
    }

    private function currentOpenCash(): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM cash_registers WHERE user_id = ? AND status = 'open' ORDER BY opened_at DESC LIMIT 1");
        $stmt->execute([$_SESSION['user_id'] ?? 0]);
        $cash = $stmt->fetch();
        return $cash ?: null;
    }

    private function isRealCustomer(?array $customer): bool {
        return $customer !== null
            && (int) ($customer['id'] ?? 0) > 0
            && (int) ($customer['is_default'] ?? 0) === 0;
    }

    private function lockProduct(int $productId): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM products WHERE id = ?' . selectForUpdateClause());
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        return $product ?: null;
    }

    private function paymentFromPost(float $totalAmount, int $customerId): array {
        $method = $_POST['payment_method'] ?? 'cash';
        if (!array_key_exists($method, PAYMENT_METHODS)) {
            throw new RuntimeException('Forma de pagamento invalida.');
        }
        $installments = max(1, min(12, (int) ($_POST['installments'] ?? 1)));
        if (!in_array($method, ['credit_card', 'store_credit'], true)) {
            $installments = 1;
        }

        $amount = normalizeMoney($_POST['payment_amount'] ?? $totalAmount);
        $change = 0.0;
        $netAmount = $amount;
        if ($method === 'cash') {
            if ($amount < $totalAmount) {
                throw new RuntimeException('Valor recebido em dinheiro menor que o total.');
            }
            $change = round($amount - $totalAmount, 2);
            $netAmount = $totalAmount;
        } elseif (round($amount - $totalAmount, 2) !== 0.0) {
            throw new RuntimeException('Valor do pagamento deve ser igual ao total.');
        }

        if ($method === 'store_credit') {
            $customer = $this->findCustomer($customerId);
            if (!$customer || empty($customer['credit_enabled'])) {
                throw new RuntimeException('Cliente nao possui crediario habilitado.');
            }
            if ($this->hasBlockedStoreCredit($customerId)) {
                throw new RuntimeException('Cliente com debitos em atraso. Novas vendas a prazo bloqueadas.');
            }
            $available = round((float) $customer['credit_limit'] - $this->customerOpenStoreCredit($customerId), 2);
            if (!hasPermission('admin') && $netAmount > $available) {
                throw new RuntimeException('Limite de crediario insuficiente para esta venda.');
            }
        }
        if ($method === 'customer_credit') {
            $customer = $this->findCustomer($customerId);
            if (!$this->isRealCustomer($customer)) {
                throw new RuntimeException('Credito do cliente exige cliente cadastrado.');
            }
            if ($netAmount > $this->customerAvailableCredit($customerId)) {
                throw new RuntimeException('Credito disponivel do cliente insuficiente.');
            }
            $installments = 1;
        }

        return [
            'payment_method' => $method,
            'amount' => $amount,
            'installments' => $installments,
            'change_amount' => $change,
            'net_amount' => $netAmount,
        ];
    }

    private function createReceivables(int $saleId, int $customerId, array $payment): void {
        $perInstallment = round($payment['net_amount'] / $payment['installments'], 2);
        for ($i = 1; $i <= $payment['installments']; $i++) {
            $amount = $i === $payment['installments']
                ? round($payment['net_amount'] - ($perInstallment * ($payment['installments'] - 1)), 2)
                : $perInstallment;
            $dueDate = (new DateTimeImmutable('today'))->modify('+' . ($i * 30) . ' days')->format('Y-m-d');
            $this->pdo->prepare("
                INSERT INTO accounts_receivable
                    (sale_id, customer_id, description, installment_no, installments, due_date, amount, status, source, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'open', ?, ?)
            ")->execute([
                $saleId,
                $customerId,
                $payment['payment_method'] === 'credit_card' ? 'Parcelamento cartao' : 'Crediario da loja',
                $i,
                $payment['installments'],
                $dueDate,
                $amount,
                $payment['payment_method'] === 'credit_card' ? 'credit_card' : 'store_credit',
                dbNow(),
            ]);
        }
    }

    private function customerOpenStoreCredit(int $customerId): float {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(amount - received_amount), 0)
            FROM accounts_receivable
            WHERE customer_id = ?
              AND source = 'store_credit'
              AND status IN ('open', 'partial')
        ");
        $stmt->execute([$customerId]);
        return round((float) $stmt->fetchColumn(), 2);
    }

    private function hasBlockedStoreCredit(int $customerId): bool {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM accounts_receivable
            WHERE customer_id = ?
              AND source = 'store_credit'
              AND status IN ('open', 'partial')
              AND due_date < ?
        ");
        $stmt->execute([$customerId, dbDatePlusDays(-30)]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function customerAvailableCredit(int $customerId): float {
        $customer = $this->findCustomer($customerId);
        if (!$this->isRealCustomer($customer)) {
            return 0.0;
        }
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(amount - used_amount), 0)
            FROM customer_credits
            WHERE customer_id = ?
              AND status IN ('open', 'partial')
        ");
        $stmt->execute([$customerId]);
        return round((float) $stmt->fetchColumn(), 2);
    }

    private function consumeCustomerCredit(int $customerId, float $amount, int $saleId): void {
        $remaining = round($amount, 2);
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM customer_credits
            WHERE customer_id = ?
              AND status IN ('open', 'partial')
            ORDER BY created_at ASC, id ASC
            " . selectForUpdateClause()
        );
        $stmt->execute([$customerId]);
        foreach ($stmt->fetchAll() as $credit) {
            if ($remaining <= 0) {
                break;
            }
            $available = round((float) $credit['amount'] - (float) $credit['used_amount'], 2);
            if ($available <= 0) {
                continue;
            }
            $usedNow = min($available, $remaining);
            $newUsed = round((float) $credit['used_amount'] + $usedNow, 2);
            $status = $newUsed >= (float) $credit['amount'] ? 'used' : 'partial';
            $this->pdo->prepare("
                UPDATE customer_credits
                SET used_amount = ?, status = ?, used_sale_id = ?, updated_at = ?
                WHERE id = ?
            ")->execute([$newUsed, $status, $saleId, dbNow(), $credit['id']]);
            $remaining = round($remaining - $usedNow, 2);
        }
        if ($remaining > 0.009) {
            throw new RuntimeException('Credito disponivel do cliente insuficiente.');
        }
    }

    private function maxDiscountAllowed(): float {
        if (hasPermission('admin')) {
            return 100.0;
        }
        if (hasPermission('manager')) {
            return DISCOUNT_MANAGER_LIMIT_PERCENT;
        }
        return DISCOUNT_FREE_LIMIT_PERCENT;
    }

    private function assertDiscountAuthority(float $discountPercent, string $approvalEmail, string $approvalPassword): void {
        $discountPercent = round($discountPercent, 4);
        if ($discountPercent <= $this->maxDiscountAllowed()) {
            return;
        }

        $requiredRole = $discountPercent > DISCOUNT_MANAGER_LIMIT_PERCENT ? 'admin' : 'manager';
        if (!$this->hasAuthority($requiredRole, $approvalEmail, $approvalPassword)) {
            throw new RuntimeException('Desconto acima da alcada exige autorizacao de ' . ($requiredRole === 'admin' ? 'administrador.' : 'gerente.'));
        }
    }

    private function hasAuthority(string $requiredRole, string $approvalEmail, string $approvalPassword): bool {
        if (hasPermission($requiredRole)) {
            return true;
        }
        return Security::verifyCredentialsForRole($approvalEmail, $approvalPassword, $requiredRole) !== null;
    }

    private function generateSaleNumber(): string {
        return 'VD-' . date('Ymd-His') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }

    private function renderQuotationDocument(array $quotation, array $items): string {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <title>Orcamento <?php echo htmlspecialchars($quotation['quotation_number']); ?></title>
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
            <h1><?php echo htmlspecialchars(APP_NAME); ?> - Orcamento <?php echo htmlspecialchars($quotation['quotation_number']); ?></h1>
            <div class="meta">
                <?php echo htmlspecialchars(COMPANY_LEGAL_NAME); ?> - CNPJ <?php echo htmlspecialchars(COMPANY_CNPJ); ?><br>
                <?php echo htmlspecialchars(COMPANY_ADDRESS); ?><br>
                Cliente: <?php echo htmlspecialchars($quotation['customer_name'] ?: ($quotation['customer_name_db'] ?? '-')); ?> |
                Validade: <?php echo htmlspecialchars(formatDate($quotation['valid_until'], 'd/m/Y')); ?>
            </div>
            <table>
                <thead><tr><th>Produto</th><th>Qtd.</th><th>Preco</th><th>Total</th></tr></thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td><?php echo htmlspecialchars(formatQuantity($item['quantity'])); ?></td>
                            <td><?php echo htmlspecialchars(formatMoney((float) $item['unit_price'])); ?></td>
                            <td><?php echo htmlspecialchars(formatMoney((float) $item['line_total'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="totals">
                Subtotal: <?php echo htmlspecialchars(formatMoney((float) $quotation['subtotal'])); ?><br>
                Desconto: <?php echo htmlspecialchars(formatMoney((float) $quotation['discount_amount'])); ?><br>
                <strong>Total: <?php echo htmlspecialchars(formatMoney((float) $quotation['total_amount'])); ?></strong>
            </div>
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
