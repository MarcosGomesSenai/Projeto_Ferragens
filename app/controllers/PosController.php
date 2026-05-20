<?php
/**
 * PDV de balcao.
 *
 * Mantem a venda atomica: cabecalho, itens, estoque, pagamentos e contas a receber.
 */

class PosController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function index(): void {
        Security::checkPermissions('seller');
        $cashRegister = $this->currentOpenCash();
        $customers = $this->pdo->query("
            SELECT id, name, customer_type, credit_enabled, credit_limit
            FROM customers
            WHERE status = 'active'
            ORDER BY is_default DESC, name ASC
        ")->fetchAll();
        require_once APP_PATH . '/views/pos/index.php';
    }

    public function searchProduct(): void {
        Security::checkPermissions('seller');
        $q = sanitize($_GET['q'] ?? '');
        $params = [];
        $where = ["(p.status = 'active' OR (p.status = 'discontinued' AND p.quantity > 0))"];
        if ($q !== '') {
            $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.brand LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%");
        }

        $orderBy = $q !== ''
            ? 'CASE WHEN p.sku = ? THEN 0 WHEN p.sku LIKE ? THEN 1 WHEN p.name LIKE ? THEN 2 ELSE 3 END, p.name ASC'
            : 'COALESCE(p.updated_at, p.created_at) DESC, p.name ASC';
        if ($q !== '') {
            array_push($params, $q, "$q%", "$q%");
        }

        $stmt = $this->pdo->prepare("
            SELECT p.id, p.sku AS barcode, p.name, p.unit_of_measure, p.quantity,
                   p.sale_price, p.wholesale_price
            FROM products p
            WHERE " . implode(' AND ', $where) . "
            ORDER BY $orderBy
            LIMIT 20
        ");
        $stmt->execute($params);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
    }

    public function checkout(): void {
        Security::checkPermissions('seller');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('pos');
        }
        Security::validateRequest();

        $cashRegister = $this->currentOpenCash();
        if (!$cashRegister) {
            setFlashMessage('error', 'Abra o caixa antes de iniciar uma venda.');
            redirect('pos');
        }

        $items = json_decode($_POST['items_json'] ?? '[]', true);
        $payments = json_decode($_POST['payments_json'] ?? '[]', true);
        $customerId = (int) ($_POST['customer_id'] ?? 1);
        $overallDiscount = normalizeMoney($_POST['overall_discount'] ?? 0);
        $notes = sanitize($_POST['notes'] ?? '');

        if (!is_array($items) || count($items) === 0) {
            setFlashMessage('error', 'Adicione ao menos um item para finalizar a venda.');
            redirect('pos');
        }
        if (!is_array($payments) || count($payments) === 0) {
            setFlashMessage('error', 'Informe ao menos uma forma de pagamento.');
            redirect('pos');
        }

        $customer = $this->findCustomer($customerId) ?? $this->findCustomer(1);
        $validatedItems = [];
        $subtotal = 0.0;
        $itemsDiscount = 0.0;
        $storeCreditAmount = 0.0;
        $customerCreditAmount = 0.0;
        $belowCostItems = [];
        $negativeStockItems = [];

        try {
            $this->pdo->beginTransaction();
            $timestamp = dbNow();

            foreach ($items as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $quantity = normalizeQuantity($item['quantity'] ?? 0);
                $discountPercent = max(0, (float) ($item['discount_percent'] ?? 0));

                if ($productId <= 0 || $quantity <= 0) {
                    throw new RuntimeException('Item de venda invalido.');
                }

                $product = $this->lockProduct($productId);
                if (!$product || !in_array($product['status'], ['active', 'discontinued'], true)) {
                    throw new RuntimeException('Produto indisponivel para venda.');
                }

                $basePrice = $customer && $customer['customer_type'] === 'professional' && !empty($product['wholesale_price'])
                    ? (float) $product['wholesale_price']
                    : (float) $product['sale_price'];
                $unitPrice = round($basePrice, 2);
                if ($discountPercent > 100) {
                    throw new RuntimeException('Desconto de item invalido.');
                }
                $this->assertDiscountAuthority($discountPercent);
                $lineSubtotal = round($unitPrice * $quantity, 2);
                $lineDiscount = round($lineSubtotal * ($discountPercent / 100), 2);
                $lineTotal = round($lineSubtotal - $lineDiscount, 2);

                if ((float) $product['quantity'] < $quantity) {
                    if (!hasPermission('admin')) {
                        throw new RuntimeException('Estoque insuficiente para ' . $product['name'] . '. Disponivel: ' . formatQuantity($product['quantity']) . '.');
                    }
                    $negativeStockItems[] = $product['name'];
                }

                $validatedItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $lineDiscount,
                    'line_total' => $lineTotal,
                ];
                $subtotal += $lineSubtotal;
                $itemsDiscount += $lineDiscount;
            }

            $totalAmount = round($subtotal - $itemsDiscount - $overallDiscount, 2);
            if ($totalAmount < 0) {
                throw new RuntimeException('Desconto total invalido.');
            }
            $effectiveDiscountPercent = $subtotal > 0 ? (($itemsDiscount + $overallDiscount) / $subtotal) * 100 : 0;
            $this->assertDiscountAuthority($effectiveDiscountPercent);

            $totalBeforeOverallDiscount = max(0.01, $subtotal - $itemsDiscount);
            $allocatedOverallDiscount = 0.0;
            $lastItemIndex = array_key_last($validatedItems);
            foreach ($validatedItems as $itemIndex => &$validatedItem) {
                $overallShare = $overallDiscount > 0
                    ? ($itemIndex === $lastItemIndex
                        ? round($overallDiscount - $allocatedOverallDiscount, 2)
                        : round($overallDiscount * ($validatedItem['line_total'] / $totalBeforeOverallDiscount), 2))
                    : 0.0;
                $allocatedOverallDiscount += $overallShare;
                $finalLineTotal = round($validatedItem['line_total'] - $overallShare, 2);
                $validatedItem['overall_discount_share'] = $overallShare;
                $validatedItem['total_discount_amount'] = round($validatedItem['discount_amount'] + $overallShare, 2);
                $validatedItem['final_line_total'] = $finalLineTotal;
                $minimumLineCost = round((float) $validatedItem['product']['cost_price'] * $validatedItem['quantity'], 2);
                if ($finalLineTotal < $minimumLineCost) {
                    if (!hasPermission('admin')) {
                        throw new RuntimeException('Desconto venderia o item ' . $validatedItem['product']['name'] . ' abaixo do custo.');
                    }
                    $belowCostItems[] = $validatedItem['product']['name'];
                }
            }
            unset($validatedItem);

            $paymentSummary = $this->validatePayments($payments, $totalAmount, $customer);
            $storeCreditAmount = $paymentSummary['store_credit_amount'];
            $customerCreditAmount = $paymentSummary['customer_credit_amount'];

            if ($storeCreditAmount > 0 && (!$customer || empty($customer['credit_enabled']))) {
                throw new RuntimeException('Cliente nao possui crediario habilitado.');
            }
            if ($storeCreditAmount > 0) {
                if ($this->hasBlockedStoreCredit((int) ($customer['id'] ?? 0))) {
                    throw new RuntimeException('Cliente com debitos em atraso. Novas vendas a prazo bloqueadas.');
                }
                $openCredit = $this->customerOpenStoreCredit((int) ($customer['id'] ?? 0));
                $availableCredit = round((float) ($customer['credit_limit'] ?? 0) - $openCredit, 2);
                if (!hasPermission('admin') && $storeCreditAmount > $availableCredit) {
                    throw new RuntimeException('Limite de crediario insuficiente para esta venda.');
                }
            }
            if ($customerCreditAmount > 0) {
                $customerIdForCredit = (int) ($customer['id'] ?? 0);
                if (!$this->isRealCustomer($customer)) {
                    throw new RuntimeException('Credito do cliente exige cliente cadastrado na venda.');
                }
                if ($customerCreditAmount > $this->customerAvailableCredit($customerIdForCredit)) {
                    throw new RuntimeException('Credito disponivel do cliente insuficiente.');
                }
            }

            $saleNumber = $this->generateSaleNumber();
            $stmt = $this->pdo->prepare("
                INSERT INTO sales
                    (sale_number, customer_id, cash_register_id, user_id, subtotal, discount_amount, total_amount, notes, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?)
            ");
            $stmt->execute([
                $saleNumber,
                $customer['id'] ?? 1,
                $cashRegister['id'],
                $_SESSION['user_id'],
                round($subtotal, 2),
                round($itemsDiscount + $overallDiscount, 2),
                $totalAmount,
                $notes,
                $timestamp,
            ]);
            $saleId = (int) $this->pdo->lastInsertId();

            foreach ($validatedItems as $item) {
                $product = $item['product'];
                $newQuantity = round((float) $product['quantity'] - $item['quantity'], 3);

                $saleItemStmt = $this->pdo->prepare("
                    INSERT INTO sale_items
                        (sale_id, product_id, sku_snapshot, product_name, quantity, unit_price,
                         cost_price, discount_percent, discount_amount, line_total)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $saleItemStmt->execute([
                    $saleId,
                    $product['id'],
                    $product['sku'],
                    $product['name'],
                    $item['quantity'],
                    $item['unit_price'],
                    $product['cost_price'],
                    $item['discount_percent'],
                    $item['total_discount_amount'],
                    $item['final_line_total'],
                ]);
                $saleItemId = (int) $this->pdo->lastInsertId();

                $this->pdo->prepare('UPDATE products SET quantity = ? WHERE id = ?')->execute([$newQuantity, $product['id']]);
                $this->pdo->prepare("
                    INSERT INTO stock_movements
                        (product_id, user_id, type, quantity, old_quantity, new_quantity, sale_id, sale_item_id, reason, date)
                    VALUES (?, ?, 'exit', ?, ?, ?, ?, ?, 'Venda PDV', ?)
                ")->execute([
                    $product['id'],
                    $_SESSION['user_id'],
                    $item['quantity'],
                    $product['quantity'],
                    $newQuantity,
                    $saleId,
                    $saleItemId,
                    $timestamp,
                ]);
            }

            foreach ($paymentSummary['payments'] as $payment) {
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
                    VALUES (?, ?, ?, 'sale', ?, ?, 'Venda PDV', ?)
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
                    $this->createReceivables($saleId, $customer['id'] ?? 1, $payment);
                }
                if ($payment['payment_method'] === 'customer_credit') {
                    $this->consumeCustomerCredit((int) ($customer['id'] ?? 0), (float) $payment['net_amount'], $saleId);
                }
            }

            $ledgerIncome = round($totalAmount - $customerCreditAmount, 2);
            if ($ledgerIncome > 0) {
                $this->pdo->prepare("
                    INSERT INTO financial_ledger (source_table, source_id, entry_type, amount, description, created_by, created_at)
                    VALUES ('sales', ?, 'income', ?, ?, ?, ?)
                ")->execute([$saleId, $ledgerIncome, 'Venda PDV ' . $saleNumber, $_SESSION['user_id'], $timestamp]);
            }

            $this->pdo->commit();
            Security::auditLog('sale_completed', [
                'module' => 'sales',
                'record_id' => (string) $saleId,
                'after' => [
                    'sale_number' => $saleNumber,
                    'customer_id' => $customer['id'] ?? 1,
                    'total_amount' => $totalAmount,
                    'notes' => $notes,
                    'below_cost_items' => $belowCostItems,
                    'negative_stock_items' => $negativeStockItems,
                ],
            ]);
            setFlashMessage('success', 'Venda finalizada com sucesso. Numero ' . $saleNumber . '.');
            redirect('sales', ['action' => 'view', 'id' => $saleId]);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            setFlashMessage('error', $e->getMessage());
            redirect('pos');
        }
    }

    private function validatePayments(array $payments, float $totalAmount, ?array $customer): array {
        $normalized = [];
        $sum = 0.0;
        $cashReceived = 0.0;
        $storeCreditAmount = 0.0;
        $customerCreditAmount = 0.0;

        foreach ($payments as $payment) {
            $method = $payment['payment_method'] ?? '';
            $amount = normalizeMoney($payment['amount'] ?? 0);
            $installments = max(1, min(12, (int) ($payment['installments'] ?? 1)));

            if (!array_key_exists($method, PAYMENT_METHODS) || $amount <= 0) {
                continue;
            }

            if ($method === 'debit_card') {
                $installments = 1;
            }
            if (!in_array($method, ['credit_card', 'store_credit'], true)) {
                $installments = 1;
            }

            $sum += $amount;
            if ($method === 'cash') {
                $cashReceived += $amount;
            }
            if ($method === 'store_credit') {
                $storeCreditAmount += $amount;
            }
            if ($method === 'customer_credit') {
                $customerCreditAmount += $amount;
            }

            $normalized[] = [
                'payment_method' => $method,
                'amount' => $amount,
                'installments' => $installments,
                'change_amount' => 0.0,
                'net_amount' => $amount,
            ];
        }

        if (!$normalized) {
            throw new RuntimeException('Nenhum pagamento valido informado.');
        }

        $change = 0.0;
        if ($sum > $totalAmount) {
            if ($cashReceived <= 0) {
                throw new RuntimeException('Valor pago maior que o total sem pagamento em dinheiro para gerar troco.');
            }
            $change = round($sum - $totalAmount, 2);
            if ($change > $cashReceived) {
                throw new RuntimeException('Troco maior que o valor recebido em dinheiro.');
            }
        }
        $netPaid = round($sum - $change, 2);
        if (abs($netPaid - round($totalAmount, 2)) > 0.01) {
            throw new RuntimeException('A soma dos pagamentos nao confere com o total da venda.');
        }

        if ($change > 0) {
            foreach ($normalized as &$payment) {
                if ($payment['payment_method'] === 'cash') {
                    $payment['change_amount'] = $change;
                    $payment['net_amount'] = round($payment['amount'] - $change, 2);
                    break;
                }
            }
            unset($payment);
        }

        return [
            'payments' => $normalized,
            'store_credit_amount' => $storeCreditAmount,
            'customer_credit_amount' => $customerCreditAmount,
        ];
    }

    private function createReceivables(int $saleId, int $customerId, array $payment): void {
        $timestamp = dbNow();
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
                $timestamp,
            ]);
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

    private function assertDiscountAuthority(float $discountPercent): void {
        $discountPercent = round($discountPercent, 4);
        if ($discountPercent <= $this->maxDiscountAllowed()) {
            return;
        }

        $requiredRole = $discountPercent > DISCOUNT_MANAGER_LIMIT_PERCENT ? 'admin' : 'manager';
        throw new RuntimeException('Desconto acima da alcada do seu perfil. Entre com um usuario ' . ($requiredRole === 'admin' ? 'administrador' : 'gerente') . ' para aplicar esse desconto.');
    }

    private function customerOpenStoreCredit(int $customerId): float {
        if ($customerId <= 0) {
            return 0.0;
        }
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
        if ($customerId <= 0) {
            return false;
        }
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

    private function currentOpenCash(): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM cash_registers WHERE user_id = ? AND status = 'open' ORDER BY opened_at DESC LIMIT 1");
        $stmt->execute([$_SESSION['user_id'] ?? 0]);
        $cash = $stmt->fetch();
        return $cash ?: null;
    }

    private function findCustomer(int $customerId): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM customers WHERE id = ? AND status = "active" LIMIT 1');
        $stmt->execute([$customerId]);
        $customer = $stmt->fetch();
        return $customer ?: null;
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

    private function generateSaleNumber(): string {
        return 'VD-' . date('Ymd-His') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }
}
