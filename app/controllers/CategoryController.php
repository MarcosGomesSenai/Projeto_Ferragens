<?php
/**
 * Controlador de categorias.
 */

class CategoryController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function index(): void {
        Security::checkPermissions('operator');

        $stmt = $this->pdo->query("
            SELECT id, parent_id, code, name, description, status
            FROM categories
            ORDER BY name ASC
        ");
        $categories = $stmt->fetchAll();
        $editCategory = null;
        $editId = (int) ($_GET['edit_id'] ?? 0);
        if ($editId > 0) {
            $stmt = $this->pdo->prepare('SELECT id, parent_id, code, name, description, status FROM categories WHERE id = ?');
            $stmt->execute([$editId]);
            $editCategory = $stmt->fetch() ?: null;
        }

        require_once APP_PATH . '/views/products/categories.php';
    }

    public function save(): void {
        Security::checkPermissions('operator');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('categories');
        }
        Security::validateRequest();

        $data = $this->dataFromPost();
        $errors = $this->validate($data);
        if ($errors) {
            setFlashMessage('error', implode("
", $errors));
            redirect('categories');
        }

        $this->pdo->prepare("
            INSERT INTO categories (parent_id, code, name, description, status)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$data['parent_id'], $data['code'], $data['name'], $data['description'], $data['status']]);
        $id = (int) $this->pdo->lastInsertId();

        Security::auditLog('category_created', [
            'module' => 'categories',
            'record_id' => (string) $id,
            'after' => $data,
        ]);
        setFlashMessage('success', 'Categoria cadastrada com sucesso.');
        redirect('categories');
    }

    public function update(): void {
        Security::checkPermissions('operator');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('categories');
        }
        Security::validateRequest();

        $id = (int) ($_POST['id'] ?? 0);
        $before = $this->find($id);
        if (!$before) {
            setFlashMessage('error', 'Categoria nao encontrada.');
            redirect('categories');
        }

        $data = $this->dataFromPost($id);
        $errors = $this->validate($data, $id);
        if ($errors) {
            setFlashMessage('error', implode("
", $errors));
            redirect('categories', ['edit_id' => $id]);
        }

        $this->pdo->prepare("
            UPDATE categories
            SET parent_id = ?, code = ?, name = ?, description = ?, status = ?, updated_at = ?
            WHERE id = ?
        ")->execute([$data['parent_id'], $data['code'], $data['name'], $data['description'], $data['status'], dbNow(), $id]);

        Security::auditLog('category_updated', [
            'module' => 'categories',
            'record_id' => (string) $id,
            'before' => $before,
            'after' => $data,
        ]);
        setFlashMessage('success', 'Categoria atualizada com sucesso.');
        redirect('categories');
    }

    public function delete(): void {
        Security::checkPermissions('manager');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('categories');
        }
        Security::validateRequest();

        $id = (int) ($_POST['id'] ?? 0);
        $category = $this->find($id);
        if (!$category) {
            setFlashMessage('error', 'Categoria nao encontrada.');
            redirect('categories');
        }
        if ($this->hasLinks($id)) {
            setFlashMessage('error', 'Categoria possui produtos ou subcategorias vinculadas.');
            redirect('categories');
        }

        $this->pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
        Security::auditLog('category_deleted', [
            'module' => 'categories',
            'record_id' => (string) $id,
            'before' => $category,
        ]);
        setFlashMessage('success', 'Categoria excluida com sucesso.');
        redirect('categories');
    }

    private function dataFromPost(?int $currentId = null): array {
        return [
            'parent_id' => null,
            'code' => strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', sanitize($_POST['code'] ?? '')), 0, 12)) ?: null,
            'name' => sanitize($_POST['name'] ?? ''),
            'description' => sanitize($_POST['description'] ?? ''),
            'status' => in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : 'active',
        ];
    }

    private function validate(array $data, ?int $ignoreId = null): array {
        $errors = [];
        if ($data['name'] === '') {
            $errors[] = 'Nome da categoria e obrigatorio.';
        }

        $stmt = $this->pdo->prepare('SELECT id FROM categories WHERE name = ? AND IFNULL(parent_id, 0) = ?' . ($ignoreId ? ' AND id <> ?' : '') . ' LIMIT 1');
        $params = [$data['name'], (int) ($data['parent_id'] ?? 0)];
        if ($ignoreId) {
            $params[] = $ignoreId;
        }
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $errors[] = 'Ja existe categoria com este nome no mesmo nivel.';
        }

        if ($data['code']) {
            $stmt = $this->pdo->prepare('SELECT id FROM categories WHERE code = ?' . ($ignoreId ? ' AND id <> ?' : '') . ' LIMIT 1');
            $params = [$data['code']];
            if ($ignoreId) {
                $params[] = $ignoreId;
            }
            $stmt->execute($params);
            if ($stmt->fetch()) {
                $errors[] = 'Codigo de categoria ja utilizado.';
            }
        }

        return $errors;
    }

    private function find(int $id): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        $category = $stmt->fetch();
        return $category ?: null;
    }

    private function hasLinks(int $id): bool {
        $checks = [
            'SELECT COUNT(*) FROM products WHERE category_id = ? OR subcategory_id = ?' => [$id, $id],
            'SELECT COUNT(*) FROM categories WHERE parent_id = ?' => [$id],
        ];
        foreach ($checks as $sql => $params) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            if ((int) $stmt->fetchColumn() > 0) {
                return true;
            }
        }
        return false;
    }
}
