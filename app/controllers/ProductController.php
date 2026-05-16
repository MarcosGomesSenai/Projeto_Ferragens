<?php
/**
 * Controlador de Produtos.
 *
 * Regras principais: SKU unico e imutavel apos movimentacao,
 * preco de venda acima do custo, estoque alterado somente por movimentacoes.
 */

class ProductController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function index(): void {
        Security::checkPermissions('operator');

        $sql = "
            SELECT p.*, c.name AS category_name, sc.name AS subcategory_name,
                   s.name AS supplier_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN categories sc ON p.subcategory_id = sc.id
            LEFT JOIN suppliers s ON p.supplier_id = s.id
        ";
        $params = [];
        $where = [];

        $search = sanitize($_GET['search'] ?? '');
        $category = (int) ($_GET['category_id'] ?? 0);
        $status = sanitize($_GET['status'] ?? '');
        $stockAlert = sanitize($_GET['stock_alert'] ?? '');

        if ($search !== '') {
            $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.brand LIKE ?)';
            array_push($params, "%$search%", "%$search%", "%$search%");
        }
        if ($category > 0) {
            $where[] = '(p.category_id = ? OR p.subcategory_id = ?)';
            array_push($params, $category, $category);
        }
        if ($status !== '') {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }
        if ($stockAlert === 'low') {
            $where[] = '((p.reorder_point > 0 AND p.quantity <= p.reorder_point) OR (p.reorder_point <= 0 AND p.quantity <= p.min_quantity))';
        } elseif ($stockAlert === 'critical') {
            $where[] = 'p.quantity <= ?';
            $params[] = CRITICAL_STOCK_THRESHOLD;
        }

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $allowedSorts = ['name', 'sku', 'sale_price', 'quantity', 'status'];
        $sort = in_array($_GET['sort'] ?? '', $allowedSorts, true) ? $_GET['sort'] : 'name';
        $order = strtolower($_GET['order'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY p.$sort $order";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();
        $categories = $this->fetchCategories();

        require_once APP_PATH . '/views/products/list.php';
    }

    public function add(): void {
        Security::checkPermissions('operator');
        $categories = $this->fetchCategories();
        $suppliers = $this->fetchSuppliers();
        $product = null;
        require_once APP_PATH . '/views/products/add.php';
    }

    public function save(): void {
        Security::checkPermissions('operator');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('products', ['action' => 'add']);
        }
        Security::validateRequest();

        $data = $this->productDataFromPost();
        $initialQuantity = normalizeQuantity($_POST['initial_quantity'] ?? 0);
        $errors = $this->validateProductData($data);

        if ($errors) {
            setFlashMessage('error', implode("
", $errors));
            redirect('products', ['action' => 'add']);
        }

        try {
            $this->pdo->beginTransaction();

            $sql = "
                INSERT INTO products
                    (sku, name, description, category_id, subcategory_id,
                     supplier_id, alternate_supplier_id, brand, unit_of_measure,
                     purchase_unit, conversion_factor, cost_price, sale_price,
                     wholesale_price, margin_percent, markup_percent, quantity,
                     min_quantity, reorder_point, status, notes)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?)
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['sku'], $data['name'], $data['description'],
                $data['category_id'], $data['subcategory_id'], $data['supplier_id'],
                $data['alternate_supplier_id'], $data['brand'], $data['unit_of_measure'],
                $data['purchase_unit'], $data['conversion_factor'], $data['cost_price'],
                $data['sale_price'], $data['wholesale_price'], $data['margin_percent'],
                $data['markup_percent'], $data['min_quantity'], $data['reorder_point'],
                $data['status'], $data['notes'],
            ]);

            $productId = (int) $this->pdo->lastInsertId();

            if ($initialQuantity > 0) {
                $this->applyInitialStock($productId, $initialQuantity, $data['cost_price']);
            }

            $this->pdo->commit();
            Security::auditLog('product_created', [
                'module' => 'products',
                'record_id' => (string) $productId,
                'after' => $data + ['initial_quantity' => $initialQuantity],
            ]);
            setFlashMessage('success', 'Produto cadastrado com sucesso.');
            redirect('products', ['action' => 'view', 'id' => $productId]);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[Ferragens Souza] Erro ao salvar produto: ' . $e->getMessage());
            setFlashMessage('error', 'Nao foi possivel salvar. Verifique se o SKU ja existe.');
            redirect('products', ['action' => 'add']);
        }
    }

    public function edit(): void {
        Security::checkPermissions('operator');
        $id = (int) ($_GET['id'] ?? 0);
        $product = $this->findById($id);
        if (!$product) {
            setFlashMessage('error', 'Produto nao encontrado.');
            redirect('products');
        }

        $categories = $this->fetchCategories();
        $suppliers = $this->fetchSuppliers();
        $hasMovements = $this->hasStockMovements($id);
        require_once APP_PATH . '/views/products/edit.php';
    }

    public function update(): void {
        Security::checkPermissions('operator');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('products');
        }
        Security::validateRequest();

        $id = (int) ($_POST['id'] ?? 0);
        $before = $this->findById($id);
        if (!$before) {
            setFlashMessage('error', 'Produto nao encontrado.');
            redirect('products');
        }

        $data = $this->productDataFromPost($before);
        $errors = $this->validateProductData($data, $id);
        if ($errors) {
            setFlashMessage('error', implode("
", $errors));
            redirect('products', ['action' => 'edit', 'id' => $id]);
        }

        try {
            $this->pdo->beginTransaction();

            $sql = "
                UPDATE products SET
                    sku = ?, name = ?, description = ?, category_id = ?,
                    subcategory_id = ?, supplier_id = ?, alternate_supplier_id = ?,
                    brand = ?, unit_of_measure = ?, purchase_unit = ?, conversion_factor = ?,
                    cost_price = ?, sale_price = ?, wholesale_price = ?, margin_percent = ?,
                    markup_percent = ?, min_quantity = ?, reorder_point = ?,
                    status = ?, notes = ?
                WHERE id = ?
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['sku'], $data['name'], $data['description'],
                $data['category_id'], $data['subcategory_id'], $data['supplier_id'],
                $data['alternate_supplier_id'], $data['brand'], $data['unit_of_measure'],
                $data['purchase_unit'], $data['conversion_factor'], $data['cost_price'],
                $data['sale_price'], $data['wholesale_price'], $data['margin_percent'],
                $data['markup_percent'], $data['min_quantity'], $data['reorder_point'],
                $data['status'], $data['notes'], $id,
            ]);

            if (round((float) $before['cost_price'], 2) !== round((float) $data['cost_price'], 2)) {
                $this->recordCostHistory($id, (float) $before['cost_price'], (float) $data['cost_price']);
            }
            $this->pdo->commit();

            Security::auditLog('product_updated', [
                'module' => 'products',
                'record_id' => (string) $id,
                'before' => $before,
                'after' => $data,
            ]);

            $message = 'Produto atualizado com sucesso.';
            if ((float) $before['cost_price'] !== (float) $data['cost_price']) {
                $message .= ' O custo foi alterado. Revise o preco de venda.';
            }
            setFlashMessage('success', $message);
            redirect('products', ['action' => 'view', 'id' => $id]);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[Ferragens Souza] Erro ao atualizar produto: ' . $e->getMessage());
            setFlashMessage('error', 'Nao foi possivel atualizar. Verifique se o SKU ja existe.');
            redirect('products', ['action' => 'edit', 'id' => $id]);
        }
    }

    public function delete(): void {
        Security::checkPermissions('manager');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('products');
        }
        Security::validateRequest();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            setFlashMessage('error', 'Produto nao encontrado.');
            redirect('products');
        }

        if ($this->hasStockMovements($id)) {
            $stmt = $this->pdo->prepare("UPDATE products SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$id]);
            Security::auditLog('product_inactivated', ['module' => 'products', 'record_id' => (string) $id]);
            setFlashMessage('warning', 'Produto possui historico e foi inativado em vez de excluido.');
            redirect('products');
        }

        $this->pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
        Security::auditLog('product_deleted', ['module' => 'products', 'record_id' => (string) $id]);
        setFlashMessage('success', 'Produto excluido com sucesso.');
        redirect('products');
    }

    public function view(): void {
        Security::checkPermissions('operator');
        $id = (int) ($_GET['id'] ?? 0);
        $product = $this->findById($id);
        if (!$product) {
            setFlashMessage('error', 'Produto nao encontrado.');
            redirect('products');
        }

        $stmt = $this->pdo->prepare("
            SELECT m.*, u.name AS user_name
            FROM stock_movements m
            LEFT JOIN users u ON m.user_id = u.id
            WHERE m.product_id = ?
            ORDER BY m.date DESC
        ");
        $stmt->execute([$id]);
        $productMovements = $stmt->fetchAll();

        require_once APP_PATH . '/views/products/view.php';
    }

    public function search(): void {
        if (!isLoggedIn()) {
            http_response_code(401);
            exit;
        }
        $q = sanitize($_GET['q'] ?? '');
        $params = [];
        $where = ["(p.status = 'active' OR (p.status = 'discontinued' AND p.quantity > 0))"];
        if ($q !== '') {
            $where[] = '(p.name LIKE ? OR p.sku LIKE ?)';
            array_push($params, "%$q%", "%$q%");
        }

        $sql = "
            SELECT p.id, p.sku, p.name, p.unit_of_measure, p.quantity,
                   p.sale_price, p.wholesale_price
            FROM products p
            WHERE " . implode(' AND ', $where) . "
            ORDER BY p.name ASC
            LIMIT 20
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
    }

    private function productDataFromPost(?array $existing = null): array {
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $subcategoryId = (int) ($_POST['subcategory_id'] ?? 0);
        $sku = sanitize($_POST['sku'] ?? '');

        if ($sku === '') {
            $sku = $this->generateCategorySku($categoryId, $subcategoryId);
        } elseif ($existing && $this->hasStockMovements((int) $existing['id'])) {
            $sku = $existing['sku'];
        }

        $cost = normalizeMoney($_POST['cost_price'] ?? 0);
        $desiredMarkup = normalizeMoney($_POST['desired_markup_percent'] ?? 0);
        $sale = normalizeMoney($_POST['sale_price'] ?? 0);
        if ($sale <= 0 && $cost > 0 && $desiredMarkup > 0) {
            $sale = round(calculatePriceWithMarkup($cost, $desiredMarkup), 2);
        }
        $wholesale = normalizeMoney($_POST['wholesale_price'] ?? 0);
        $wholesale = $wholesale > 0 ? $wholesale : null;

        return [
            'sku' => $sku,
            'name' => sanitize($_POST['name'] ?? ''),
            'description' => sanitize($_POST['description'] ?? ''),
            'category_id' => $categoryId ?: null,
            'subcategory_id' => $subcategoryId ?: null,
            'supplier_id' => (int) ($_POST['supplier_id'] ?? 0) ?: null,
            'alternate_supplier_id' => (int) ($_POST['alternate_supplier_id'] ?? 0) ?: null,
            'brand' => sanitize($_POST['brand'] ?? ''),
            'unit_of_measure' => array_key_exists($_POST['unit_of_measure'] ?? '', PRODUCT_UNITS) ? $_POST['unit_of_measure'] : 'UN',
            'purchase_unit' => array_key_exists($_POST['purchase_unit'] ?? '', PRODUCT_UNITS) ? $_POST['purchase_unit'] : 'UN',
            'conversion_factor' => max(0.0001, normalizeQuantity($_POST['conversion_factor'] ?? 1)),
            'cost_price' => $cost,
            'sale_price' => $sale,
            'wholesale_price' => $wholesale,
            'margin_percent' => round(calculateMargin($cost, $sale), 2),
            'markup_percent' => round(calculateMarkup($cost, $sale), 2),
            'min_quantity' => normalizeQuantity($_POST['min_quantity'] ?? LOW_STOCK_THRESHOLD),
            'reorder_point' => normalizeQuantity($_POST['reorder_point'] ?? 0),
            'status' => array_key_exists($_POST['status'] ?? '', PRODUCT_STATUS) ? $_POST['status'] : 'active',
            'notes' => sanitize($_POST['notes'] ?? ''),
        ];
    }

    private function validateProductData(array $data, ?int $ignoreId = null): array {
        $errors = [];
        if ($data['name'] === '') {
            $errors[] = 'Nome do produto e obrigatorio.';
        }
        if (!$data['category_id']) {
            $errors[] = 'Categoria e obrigatoria.';
        }
        if ($data['sale_price'] <= $data['cost_price']) {
            $errors[] = 'Preco de venda deve ser maior que o custo.';
        }
        if ($data['wholesale_price'] !== null) {
            if ($data['wholesale_price'] <= $data['cost_price']) {
                $errors[] = 'Preco atacado geraria prejuizo.';
            }
            if ($data['wholesale_price'] >= $data['sale_price']) {
                $errors[] = 'Preco atacado deve ser menor que o preco de varejo.';
            }
        }
        if ($data['reorder_point'] > 0 && $data['reorder_point'] < $data['min_quantity']) {
            $errors[] = 'Ponto de reposicao nao pode ser menor que o estoque minimo.';
        }
        if ($this->skuExists($data['sku'], $ignoreId)) {
            $errors[] = 'SKU ja utilizado por outro produto.';
        }
        return $errors;
    }

    private function applyInitialStock(int $productId, float $quantity, float $cost): void {
        $this->pdo->prepare('UPDATE products SET quantity = ? WHERE id = ?')->execute([$quantity, $productId]);
        $stmt = $this->pdo->prepare("
            INSERT INTO stock_movements
                (product_id, user_id, type, quantity, old_quantity, new_quantity, unit_cost, reason, date)
            VALUES (?, ?, 'entry', ?, 0, ?, ?, 'Estoque inicial', ?)
        ");
        $stmt->execute([$productId, $_SESSION['user_id'] ?? null, $quantity, $quantity, $cost, dbNow()]);
    }

    private function findById(int $id): ?array {
        $stmt = $this->pdo->prepare("
            SELECT p.*, c.name AS category_name, sc.name AS subcategory_name,
                   s.name AS supplier_name, alt.name AS alternate_supplier_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN categories sc ON p.subcategory_id = sc.id
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            LEFT JOIN suppliers alt ON p.alternate_supplier_id = alt.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        return $product ?: null;
    }

    private function hasStockMovements(int $productId): bool {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM stock_movements WHERE product_id = ?');
        $stmt->execute([$productId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function skuExists(string $sku, ?int $ignoreId = null): bool {
        $sql = 'SELECT id FROM products WHERE sku = ?';
        $params = [$sku];
        if ($ignoreId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $ignoreId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }


    /** @deprecated Substituido por skuExists() — M-03 */
    private function valueExists(string $table, string $column, string $value, ?int $ignoreId = null): bool {
        $sql = "SELECT id FROM $table WHERE $column = ?";
        $params = [$value];
        if ($ignoreId) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    private function fetchCategories(): array {
        return $this->pdo->query('SELECT id, parent_id, code, name FROM categories WHERE status = "active" ORDER BY parent_id ASC, name ASC')->fetchAll();
    }

    private function fetchSuppliers(): array {
        return $this->pdo->query('SELECT id, name FROM suppliers WHERE status = "active" ORDER BY name ASC')->fetchAll();
    }

    private function generateCategorySku(?int $categoryId, ?int $subcategoryId): string {
        $ids = array_filter([$categoryId, $subcategoryId]);
        $codes = [];
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->pdo->prepare("SELECT id, code, name FROM categories WHERE id IN ($placeholders)");
            $stmt->execute(array_values($ids));
            foreach ($stmt->fetchAll() as $row) {
                $codes[(int) $row['id']] = $row['code'] ?: strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $row['name']), 0, 3));
            }
        }
        $prefix = $codes[$categoryId] ?? 'PRD';
        if ($subcategoryId && isset($codes[$subcategoryId])) {
            $prefix .= '-' . $codes[$subcategoryId];
        }

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
        $stmt->execute([$categoryId]);
        return sprintf('%s-%04d', strtoupper($prefix), ((int) $stmt->fetchColumn()) + 1);
    }

    private function recordCostHistory(int $productId, float $oldCost, float $newCost): void {
        $this->pdo->prepare("
            INSERT INTO product_cost_history
                (product_id, old_cost, new_cost, source_table, source_id, changed_by, changed_at)
            VALUES (?, ?, ?, 'products', ?, ?, ?)
        ")->execute([$productId, $oldCost, $newCost, $productId, $_SESSION['user_id'], dbNow()]);
    }
}
