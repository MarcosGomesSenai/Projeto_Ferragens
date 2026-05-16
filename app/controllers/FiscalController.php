<?php
/**
 * Entrada fiscal manual de fornecedor, sem integracao SEFAZ nesta versao.
 */

class FiscalController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function index(): void {
        Security::checkPermissions('operator');
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $startDate = date('Y-m-01');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $endDate = date('Y-m-d');
        }
        $stmt = $this->pdo->prepare("
            SELECT fe.*, s.name AS supplier_name, u.name AS user_name
            FROM fiscal_entries fe
            INNER JOIN suppliers s ON s.id = fe.supplier_id
            LEFT JOIN users u ON u.id = fe.created_by
            WHERE fe.issue_date BETWEEN ? AND ?
            ORDER BY fe.issue_date DESC, fe.id DESC
            LIMIT 100
        ");
        $stmt->execute([$startDate, $endDate]);
        $entries = $stmt->fetchAll();
        require_once APP_PATH . '/views/fiscal/index.php';
    }

    public function add(): void {
        Security::checkPermissions('operator');
        $suppliers = $this->pdo->query("SELECT id, name, default_payment_terms FROM suppliers WHERE status = 'active' ORDER BY name ASC")->fetchAll();
        require_once APP_PATH . '/views/fiscal/add.php';
    }

    public function save(): void {
        Security::checkPermissions('operator');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('fiscal', ['action' => 'add']);
        }
        Security::validateRequest();

        $supplierId = (int) ($_POST['supplier_id'] ?? 0);
        $invoiceNumber = sanitize($_POST['invoice_number'] ?? '');
        $invoiceSeries = sanitize($_POST['invoice_series'] ?? '1') ?: '1';
        $accessKey = onlyDigits($_POST['access_key'] ?? '') ?: null;
        $issueDate = $_POST['issue_date'] ?? date('Y-m-d');
        $cst = sanitize($_POST['cst'] ?? '');
        $icmsBase = ($_POST['icms_base'] ?? '') !== '' ? normalizeMoney($_POST['icms_base']) : null;
        $paymentTerms = sanitize($_POST['payment_terms'] ?? '');
        $items = json_decode($_POST['items_json'] ?? '[]', true);
        $updateCosts = ($_POST['update_costs'] ?? '') === '1';

        if ($supplierId <= 0 || $invoiceNumber === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $issueDate) || !is_array($items) || !$items) {
            setFlashMessage('error', 'Preencha fornecedor, NF, data e itens.');
            redirect('fiscal', ['action' => 'add']);
        }

        $supplier = $this->findSupplier($supplierId);
        if (!$supplier) {
            setFlashMessage('error', 'Fornecedor nao encontrado.');
            redirect('fiscal', ['action' => 'add']);
        }
        if ($paymentTerms === '') {
            $paymentTerms = (string) ($supplier['default_payment_terms'] ?? '');
        }

        try {
            $this->pdo->beginTransaction();
            $timestamp = dbNow();
            $validated = [];
            $total = 0.0;

            foreach ($items as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $purchaseQty = normalizeQuantity($item['quantity'] ?? 0);
                $unitCost = normalizeMoney($item['unit_cost'] ?? 0);
                if ($productId <= 0 || $purchaseQty <= 0 || $unitCost < 0) {
                    throw new RuntimeException('Item de NF invalido.');
                }
                $product = $this->lockProduct($productId);
                if (!$product || $product['status'] === 'discontinued') {
                    throw new RuntimeException('Produto descontinuado nao aceita nova entrada.');
                }
                $stockQty = round($purchaseQty * (float) ($product['conversion_factor'] ?: 1), 3);
                $lineTotal = round($purchaseQty * $unitCost, 2);
                $total += $lineTotal;
                $validated[] = compact('product', 'purchaseQty', 'stockQty', 'unitCost', 'lineTotal');
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO fiscal_entries
                    (supplier_id, invoice_number, invoice_series, access_key, issue_date, total_amount,
                     cst, icms_base, status, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', ?, ?)
            ");
            $stmt->execute([$supplierId, $invoiceNumber, $invoiceSeries, $accessKey, $issueDate, round($total, 2), $cst, $icmsBase, $_SESSION['user_id'], $timestamp]);
            $entryId = (int) $this->pdo->lastInsertId();

            foreach ($validated as $item) {
                $product = $item['product'];
                $oldQty = (float) $product['quantity'];
                $newQty = round($oldQty + $item['stockQty'], 3);

                $this->pdo->prepare("
                    INSERT INTO fiscal_entry_items (fiscal_entry_id, product_id, quantity, unit_cost, total_cost)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([$entryId, $product['id'], $item['purchaseQty'], $item['unitCost'], $item['lineTotal']]);

                $oldCost = (float) $product['cost_price'];
                $costForProduct = $updateCosts ? $item['unitCost'] : $oldCost;
                if ($updateCosts && round($oldCost, 2) !== round($item['unitCost'], 2)) {
                    $this->recordCostHistory((int) $product['id'], $oldCost, $item['unitCost'], $entryId, $timestamp);
                }

                $this->pdo->prepare('UPDATE products SET quantity = ?, cost_price = ?, margin_percent = ?, markup_percent = ? WHERE id = ?')
                    ->execute([
                        $newQty,
                        $costForProduct,
                        round(calculateMargin($costForProduct, (float) $product['sale_price']), 2),
                        round(calculateMarkup($costForProduct, (float) $product['sale_price']), 2),
                        $product['id'],
                    ]);

                $this->pdo->prepare("
                    INSERT INTO stock_movements
                        (product_id, user_id, type, quantity, old_quantity, new_quantity, unit_cost,
                         supplier_id, fiscal_entry_id, invoice_number, invoice_series, reason, date)
                    VALUES (?, ?, 'entry', ?, ?, ?, ?, ?, ?, ?, ?, 'Entrada NF fornecedor', ?)
                ")->execute([
                    $product['id'],
                    $_SESSION['user_id'],
                    $item['stockQty'],
                    $oldQty,
                    $newQty,
                    $item['unitCost'],
                    $supplierId,
                    $entryId,
                    $invoiceNumber,
                    $invoiceSeries,
                    $timestamp,
                ]);
            }

            $this->createPayables($supplierId, $entryId, $invoiceNumber, round($total, 2), $issueDate, $paymentTerms, $timestamp);
            $this->pdo->commit();

            Security::auditLog('fiscal_entry_created', [
                'module' => 'fiscal',
                'record_id' => (string) $entryId,
                'after' => ['invoice_number' => $invoiceNumber, 'total_amount' => round($total, 2)],
            ]);
            setFlashMessage('success', $updateCosts
                ? 'Entrada de NF registrada com sucesso. Custos cadastrais atualizados quando houve diferenca.'
                : 'Entrada de NF registrada com sucesso.');
            redirect('fiscal');
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[Ferragens Souza] Erro ao registrar entrada fiscal: ' . $e->getMessage());
            if ($e instanceof RuntimeException) {
                setFlashMessage('error', $e->getMessage());
            } else {
                setFlashMessage('error', str_contains($e->getMessage(), 'UNIQUE') || str_contains($e->getMessage(), 'Duplicate')
                    ? 'Possivel NF duplicada para este fornecedor e serie.'
                    : 'Nao foi possivel registrar a entrada fiscal.');
            }
            redirect('fiscal', ['action' => 'add']);
        }
    }

    private function findSupplier(int $supplierId): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM suppliers WHERE id = ? AND status = "active" LIMIT 1');
        $stmt->execute([$supplierId]);
        $supplier = $stmt->fetch();
        return $supplier ?: null;
    }

    private function lockProduct(int $productId): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM products WHERE id = ?' . selectForUpdateClause());
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        return $product ?: null;
    }

    private function createPayables(int $supplierId, int $entryId, string $invoiceNumber, float $total, string $issueDate, string $paymentTerms, string $timestamp): void {
        $days = $this->parsePaymentTerms($paymentTerms);
        $installmentValue = round($total / count($days), 2);
        foreach ($days as $index => $day) {
            $amount = $index === array_key_last($days)
                ? round($total - ($installmentValue * (count($days) - 1)), 2)
                : $installmentValue;
            $dueDate = (new DateTimeImmutable($issueDate))->modify('+' . $day . ' days')->format('Y-m-d');
            $this->pdo->prepare("
                INSERT INTO accounts_payable
                    (supplier_id, fiscal_entry_id, description, due_date, amount, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'open', ?)
            ")->execute([$supplierId, $entryId, 'NF ' . $invoiceNumber . ' parcela ' . ($index + 1) . '/' . count($days), $dueDate, $amount, $timestamp]);
        }
    }

    private function parsePaymentTerms(string $terms): array {
        preg_match_all('/\d+/', $terms, $matches);
        $days = array_map('intval', $matches[0] ?? []);
        $days = array_values(array_filter($days, static fn($day) => $day >= 0));
        return $days ?: [0];
    }

    private function recordCostHistory(int $productId, float $oldCost, float $newCost, int $fiscalEntryId, string $timestamp): void {
        $this->pdo->prepare("
            INSERT INTO product_cost_history
                (product_id, old_cost, new_cost, source_table, source_id, changed_by, changed_at)
            VALUES (?, ?, ?, 'fiscal_entries', ?, ?, ?)
        ")->execute([$productId, $oldCost, $newCost, $fiscalEntryId, $_SESSION['user_id'], $timestamp]);
    }
}
