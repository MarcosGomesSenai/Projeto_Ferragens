<?php
/**
 * Controlador de Fornecedores.
 *
 * Fornecedor e pessoa juridica na primeira versao: CNPJ valido e unico.
 */

class SupplierController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function index(): void {
        Security::checkPermissions('operator');

        $sql = "
            SELECT s.*,
                   (SELECT COUNT(*) FROM products p WHERE p.supplier_id = s.id OR p.alternate_supplier_id = s.id) AS linked_products,
                   (SELECT COALESCE(SUM(amount - paid_amount), 0) FROM accounts_payable ap WHERE ap.supplier_id = s.id AND ap.status IN ('open','partial')) AS open_payables
            FROM suppliers s
        ";
        $params = [];
        $where = [];

        $search = sanitize($_GET['search'] ?? '');
        $status = sanitize($_GET['status'] ?? '');
        if ($search !== '') {
            $where[] = '(s.name LIKE ? OR s.legal_name LIKE ? OR s.cnpj LIKE ? OR s.segment LIKE ?)';
            array_push($params, "%$search%", "%$search%", "%$search%", "%$search%");
        }
        if ($status !== '') {
            $where[] = 's.status = ?';
            $params[] = $status;
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY s.name ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $suppliers = $stmt->fetchAll();

        require_once APP_PATH . '/views/suppliers/list.php';
    }

    public function add(): void {
        Security::checkPermissions('operator');
        $supplier = [];
        $isEdit = false;
        require_once APP_PATH . '/views/suppliers/add.php';
    }

    public function save(): void {
        Security::checkPermissions('operator');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('suppliers', ['action' => 'add']);
        }
        Security::validateRequest();

        $data = $this->supplierDataFromPost();
        $errors = $this->validateSupplierData($data);
        if ($errors) {
            setFlashMessage('error', implode("
", $errors));
            redirect('suppliers', ['action' => 'add']);
        }

        try {
            $sql = "
                INSERT INTO suppliers
                    (name, legal_name, trade_name, cnpj, segment, email, phone, address,
                     city, state, commercial_contact_name, commercial_contact_phone,
                     default_payment_terms, commercial_terms, credit_limit, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_values($data));
            $id = (int) $this->pdo->lastInsertId();
            Security::auditLog('supplier_created', [
                'module' => 'suppliers',
                'record_id' => (string) $id,
                'after' => $data,
            ]);
            setFlashMessage('success', 'Fornecedor cadastrado com sucesso.');
            redirect('suppliers');
        } catch (PDOException $e) {
            error_log('[Ferragens Souza] Erro ao salvar fornecedor: ' . $e->getMessage());
            setFlashMessage('error', 'Nao foi possivel salvar. Verifique se o CNPJ ja esta cadastrado.');
            redirect('suppliers', ['action' => 'add']);
        }
    }

    public function edit(): void {
        Security::checkPermissions('operator');
        $id = (int) ($_GET['id'] ?? 0);
        $supplier = $this->findById($id);
        if (!$supplier) {
            setFlashMessage('error', 'Fornecedor nao encontrado.');
            redirect('suppliers');
        }
        $isEdit = true;
        require_once APP_PATH . '/views/suppliers/edit.php';
    }

    public function update(): void {
        Security::checkPermissions('operator');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('suppliers');
        }
        Security::validateRequest();

        $id = (int) ($_POST['id'] ?? 0);
        $before = $this->findById($id);
        if (!$before) {
            setFlashMessage('error', 'Fornecedor nao encontrado.');
            redirect('suppliers');
        }

        $data = $this->supplierDataFromPost();
        $errors = $this->validateSupplierData($data, $id);
        if ($errors) {
            setFlashMessage('error', implode("
", $errors));
            redirect('suppliers', ['action' => 'edit', 'id' => $id]);
        }

        try {
            $sql = "
                UPDATE suppliers SET
                    name = ?, legal_name = ?, trade_name = ?, cnpj = ?, segment = ?,
                    email = ?, phone = ?, address = ?, city = ?, state = ?,
                    commercial_contact_name = ?, commercial_contact_phone = ?,
                    default_payment_terms = ?, commercial_terms = ?, credit_limit = ?,
                    status = ?
                WHERE id = ?
            ";
            $params = array_values($data);
            $params[] = $id;
            $this->pdo->prepare($sql)->execute($params);
            Security::auditLog('supplier_updated', [
                'module' => 'suppliers',
                'record_id' => (string) $id,
                'before' => $before,
                'after' => $data,
            ]);
            setFlashMessage('success', 'Fornecedor atualizado com sucesso.');
            redirect('suppliers');
        } catch (PDOException $e) {
            error_log('[Ferragens Souza] Erro ao atualizar fornecedor: ' . $e->getMessage());
            setFlashMessage('error', 'Nao foi possivel atualizar. Verifique se o CNPJ ja esta cadastrado.');
            redirect('suppliers', ['action' => 'edit', 'id' => $id]);
        }
    }

    public function delete(): void {
        Security::checkPermissions('manager');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('suppliers');
        }
        Security::validateRequest();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            setFlashMessage('error', 'Fornecedor nao encontrado.');
            redirect('suppliers');
        }

        if ($this->hasBlockingLinks($id)) {
            $this->pdo->prepare("UPDATE suppliers SET status = 'inactive' WHERE id = ?")->execute([$id]);
            Security::auditLog('supplier_inactivated', ['module' => 'suppliers', 'record_id' => (string) $id]);
            setFlashMessage('warning', 'Fornecedor possui vinculos e foi inativado para preservar o historico.');
            redirect('suppliers');
        }

        $this->pdo->prepare('DELETE FROM suppliers WHERE id = ?')->execute([$id]);
        Security::auditLog('supplier_deleted', ['module' => 'suppliers', 'record_id' => (string) $id]);
        setFlashMessage('success', 'Fornecedor excluido com sucesso.');
        redirect('suppliers');
    }

    private function supplierDataFromPost(): array {
        $cnpj = onlyDigits($_POST['cnpj'] ?? '');
        $name = sanitize($_POST['name'] ?? '');
        $legalName = sanitize($_POST['legal_name'] ?? '');
        return [
            'name' => $name !== '' ? $name : $legalName,
            'legal_name' => $legalName,
            'trade_name' => sanitize($_POST['trade_name'] ?? ''),
            'cnpj' => $cnpj,
            'segment' => sanitize($_POST['segment'] ?? ''),
            'email' => strtolower(trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL))),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'address' => sanitize($_POST['address'] ?? ''),
            'city' => sanitize($_POST['city'] ?? ''),
            'state' => strtoupper(substr(sanitize($_POST['state'] ?? ''), 0, 2)),
            'commercial_contact_name' => sanitize($_POST['commercial_contact_name'] ?? ''),
            'commercial_contact_phone' => sanitize($_POST['commercial_contact_phone'] ?? ''),
            'default_payment_terms' => sanitize($_POST['default_payment_terms'] ?? ''),
            'commercial_terms' => sanitize($_POST['commercial_terms'] ?? ''),
            'credit_limit' => ($_POST['credit_limit'] ?? '') !== '' ? normalizeMoney($_POST['credit_limit']) : null,
            'status' => in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : 'active',
        ];
    }

    private function validateSupplierData(array $data, ?int $ignoreId = null): array {
        $errors = [];
        if ($data['name'] === '') {
            $errors[] = 'Nome fantasia ou razao social e obrigatorio.';
        }
        if ($data['cnpj'] === '' || !isValidCnpj($data['cnpj'])) {
            $errors[] = 'CNPJ invalido.';
        }
        if ($data['segment'] === '') {
            $errors[] = 'Segmento de atuacao e obrigatorio.';
        }
        if ($data['email'] !== '' && !isValidEmail($data['email'])) {
            $errors[] = 'Email invalido.';
        }

        $sql = 'SELECT id FROM suppliers WHERE cnpj = ?';
        $params = [$data['cnpj']];
        if ($ignoreId) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $errors[] = 'Ja existe fornecedor com este CNPJ.';
        }
        return $errors;
    }

    private function findById(int $id): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM suppliers WHERE id = ?');
        $stmt->execute([$id]);
        $supplier = $stmt->fetch();
        return $supplier ?: null;
    }

    private function hasBlockingLinks(int $id): bool {
        $checks = [
            'SELECT COUNT(*) FROM products WHERE supplier_id = ? OR alternate_supplier_id = ?' => [$id, $id],
            'SELECT COUNT(*) FROM fiscal_entries WHERE supplier_id = ?' => [$id],
            "SELECT COUNT(*) FROM accounts_payable WHERE supplier_id = ? AND status IN ('open','partial')" => [$id],
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
