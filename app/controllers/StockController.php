<?php
/**
 * Controlador de Estoque.
 *
 * Toda alteracao de saldo precisa gerar movimentacao auditavel.
 */

class StockController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function index(): void {
        $this->movements();
    }

    public function movements(): void {
        Security::checkPermissions('operator');
        $movements = $this->pdo->query("
            SELECT m.*, p.name AS product_name, p.sku, u.name AS user_name
            FROM stock_movements m
            LEFT JOIN products p ON p.id = m.product_id
            LEFT JOIN users u ON u.id = m.user_id
            ORDER BY m.date DESC
            LIMIT 200
        ")->fetchAll();
        require_once APP_PATH . '/views/stock/movements.php';
    }

    public function reverseMovement(): void {
        Security::checkPermissions('admin');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('stock', ['action' => 'movements']);
        }
        Security::validateRequest();
        if (!Security::verifyCurrentPassword($_POST['reauth_password'] ?? '')) {
            setFlashMessage('error', 'Senha de confirmacao invalida.');
            redirect('stock', ['action' => 'movements']);
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            setFlashMessage('error', 'Movimentacao invalida.');
            redirect('stock', ['action' => 'movements']);
        }

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare('SELECT * FROM stock_movements WHERE id = ?');
            $stmt->execute([$id]);
            $movement = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$movement) {
                throw new RuntimeException('Movimentacao nao encontrada.');
            }

            $productId = (int) ($movement['product_id'] ?? 0);
            if ($productId > 0) {
                $stmt = $this->pdo->prepare('SELECT quantity FROM products WHERE id = ?' . selectForUpdateClause());
                $stmt->execute([$productId]);
                $currentQty = (float) $stmt->fetchColumn();
                if (round($currentQty, 3) !== round((float) $movement['new_quantity'], 3)) {
                    throw new RuntimeException('Ja existem movimentacoes posteriores para este produto.');
                }
                $targetQty = round((float) $movement['old_quantity'], 3);
                $reverseQty = abs(round($currentQty - $targetQty, 3));
                $this->pdo->prepare('UPDATE products SET quantity = ? WHERE id = ?')
                    ->execute([$targetQty, $productId]);
                $this->pdo->prepare("
                    INSERT INTO stock_movements
                        (product_id, user_id, type, quantity, old_quantity, new_quantity, reason, approved_by, date)
                    VALUES (?, ?, 'adjustment', ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $productId,
                    $_SESSION['user_id'],
                    $reverseQty,
                    $currentQty,
                    $targetQty,
                    'Reversao da movimentacao #' . $id,
                    $_SESSION['user_id'],
                    dbNow(),
                ]);
            }

            $this->pdo->commit();
            Security::auditLog('stock_movement_reversed', [
                'module' => 'stock',
                'record_id' => (string) $id,
                'before' => $movement,
                'after' => ['new_quantity' => $movement['old_quantity']],
            ]);
            setFlashMessage('success', 'Movimentacao revertida com sucesso.');
            $this->redirectAfterMovementReverse($productId);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            setFlashMessage('error', $e->getMessage());
            redirect('stock', ['action' => 'movements']);
        }
    }

    private function redirectAfterMovementReverse(int $productId): void {
        if ($productId > 0 && ($_POST['return'] ?? '') === 'view') {
            $pid = (int) ($_POST['product_id'] ?? 0);
            if ($pid === $productId) {
                redirect('products', ['action' => 'view', 'id' => (string) $pid]);
            }
        }
        redirect('stock', ['action' => 'movements']);
    }

    public function adjustment(): void {
        Security::checkPermissions('manager');
        $products = $this->pdo->query("
            SELECT id, name, sku, quantity, unit_of_measure
            FROM products
            WHERE status IN ('active','discontinued')
            ORDER BY name ASC
        ")->fetchAll();
        $reasons = [
            'Inventario Fisico',
            'Quebra/Dano',
            'Produto Vencido',
            'Erro de Cadastro',
            'Furto/Roubo',
            'Uso Interno',
        ];
        require_once APP_PATH . '/views/stock/adjustment.php';
    }

    public function saveAdjustment(): void {
        Security::checkPermissions('manager');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('stock', ['action' => 'adjustment']);
        }
        Security::validateRequest();

        $productId = (int) ($_POST['product_id'] ?? 0);
        $physicalQuantity = normalizeQuantity($_POST['physical_quantity'] ?? 0);
        $reason = sanitize($_POST['reason'] ?? '');
        $allowedReasons = [
            'Inventario Fisico',
            'Quebra/Dano',
            'Produto Vencido',
            'Erro de Cadastro',
            'Furto/Roubo',
            'Uso Interno',
        ];
        if ($productId <= 0 || $physicalQuantity < 0 || !in_array($reason, $allowedReasons, true)) {
            setFlashMessage('error', 'Preencha produto, quantidade fisica e motivo.');
            redirect('stock', ['action' => 'adjustment']);
        }
        if (!Security::verifyCurrentPassword($_POST['reauth_password'] ?? '')) {
            setFlashMessage('error', 'Senha de confirmacao invalida.');
            redirect('stock', ['action' => 'adjustment']);
        }

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare('SELECT id, name, quantity, status, conversion_factor FROM products WHERE id = ?' . selectForUpdateClause());
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            if (!$product) {
                throw new RuntimeException('Produto nao encontrado.');
            }

            $oldQuantity = (float) $product['quantity'];
            $newQuantity = $physicalQuantity;
            $difference = round($newQuantity - $oldQuantity, 3);
            if (abs($difference) < 0.001) {
                throw new RuntimeException('A quantidade fisica informada e igual ao estoque atual.');
            }
            if ($difference > 0 && $product['status'] === 'discontinued') {
                throw new RuntimeException('Produto descontinuado nao aceita aumento de estoque.');
            }
            $lossReasons = ['Quebra/Dano', 'Produto Vencido', 'Furto/Roubo', 'Uso Interno'];
            $type = $difference < 0 && in_array($reason, $lossReasons, true) ? 'loss' : 'adjustment';
            $movementQuantity = abs($difference);

            $this->pdo->prepare('UPDATE products SET quantity = ? WHERE id = ?')->execute([$newQuantity, $productId]);
            $this->pdo->prepare("
                INSERT INTO stock_movements
                    (product_id, user_id, type, quantity, old_quantity, new_quantity, reason, is_theft_loss, approved_by, date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $productId,
                $_SESSION['user_id'],
                $type,
                $movementQuantity,
                $oldQuantity,
                $newQuantity,
                $reason,
                $reason === 'Furto/Roubo' ? 1 : 0,
                $_SESSION['user_id'],
                dbNow(),
            ]);
            $this->pdo->commit();
            Security::auditLog('stock_adjustment_created', [
                'module' => 'stock',
                'record_id' => (string) $productId,
                'after' => compact('type', 'physicalQuantity', 'reason', 'newQuantity'),
            ]);
            setFlashMessage('success', 'Movimentacao de estoque registrada com sucesso.');
            redirect('stock', ['action' => 'movements']);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            setFlashMessage('error', $e->getMessage());
            redirect('stock', ['action' => 'adjustment']);
        }
    }

    public function inventory(): void {
        Security::checkPermissions('operator');
        $products = $this->pdo->query("
            SELECT p.name, p.quantity, p.min_quantity, p.cost_price, p.sale_price, p.unit_of_measure,
                   c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            ORDER BY p.name ASC
        ")->fetchAll();

        $stats = $this->pdo->query("
            SELECT COUNT(id) AS total_items,
                   COALESCE(SUM(quantity), 0) AS total_quantity,
                   COALESCE(SUM(cost_price * quantity), 0) AS total_stock_value,
                   COALESCE(SUM(sale_price * quantity), 0) AS total_sale_value
            FROM products
        ")->fetch();
        require_once APP_PATH . '/views/stock/inventory.php';
    }

    public function lowStock(): void {
        Security::checkPermissions('operator');
        $lowStockProducts = $this->pdo->query("
            SELECT p.id, p.sku, p.name, p.quantity, p.min_quantity, p.reorder_point,
                   p.cost_price, p.sale_price, p.status, p.unit_of_measure,
                   c.name AS category_name, s.name AS supplier_name
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN suppliers s ON s.id = p.supplier_id
            WHERE p.status = 'active'
              AND (
                  (p.reorder_point > 0 AND p.quantity <= p.reorder_point)
                  OR (p.reorder_point <= 0 AND p.quantity <= p.min_quantity)
              )
            ORDER BY
                (CASE WHEN p.reorder_point > 0 THEN p.reorder_point ELSE p.min_quantity END - p.quantity) DESC,
                p.quantity ASC
        ")->fetchAll();
        require_once APP_PATH . '/views/stock/low-stock.php';
    }
}
