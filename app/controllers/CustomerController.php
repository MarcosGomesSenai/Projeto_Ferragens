<?php
/**
 * Controlador de Clientes.
 *
 * Clientes podem ser CPF, CNPJ ou Consumidor Final fixo para venda anonima.
 */

class CustomerController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function index(): void {
        Security::checkPermissions('operator');
        $search = sanitize($_GET['search'] ?? '');
        $params = [];
        $where = [];

        $sql = 'SELECT * FROM customers';
        if ($search !== '') {
            $where[] = '(name LIKE ? OR document LIKE ? OR phone LIKE ?)';
            array_push($params, "%$search%", "%$search%", "%$search%");
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY is_default DESC, name ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $customers = $stmt->fetchAll();
        require_once APP_PATH . '/views/customers/list.php';
    }

    public function add(): void {
        Security::checkPermissions('operator');
        $customer = [];
        $isEdit = false;
        require_once APP_PATH . '/views/customers/add.php';
    }

    public function save(): void {
        Security::checkPermissions('operator');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('customers', ['action' => 'add']);
        }
        Security::validateRequest();

        $data = $this->customerDataFromPost();
        $errors = $this->validateCustomerData($data);
        if ($errors) {
            setFlashMessage('error', implode("
", $errors));
            redirect('customers', ['action' => 'add']);
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO customers
                (document_type, document, name, phone, email, address, city, state,
                 customer_type, credit_enabled, credit_limit, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute(array_values($data));
        $id = (int) $this->pdo->lastInsertId();
        Security::auditLog('customer_created', ['module' => 'customers', 'record_id' => (string) $id, 'after' => $data]);
        setFlashMessage('success', 'Cliente cadastrado com sucesso.');
        redirect('customers');
    }

    public function edit(): void {
        Security::checkPermissions('operator');
        $id = (int) ($_GET['id'] ?? 0);
        $customer = $this->findById($id);
        if (!$customer || (int) $customer['is_default'] === 1) {
            setFlashMessage('error', 'Cliente nao pode ser editado.');
            redirect('customers');
        }
        $isEdit = true;
        require_once APP_PATH . '/views/customers/edit.php';
    }

    public function update(): void {
        Security::checkPermissions('operator');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('customers');
        }
        Security::validateRequest();

        $id = (int) ($_POST['id'] ?? 0);
        $before = $this->findById($id);
        if (!$before || (int) $before['is_default'] === 1) {
            setFlashMessage('error', 'Cliente nao pode ser editado.');
            redirect('customers');
        }

        $data = $this->customerDataFromPost();
        $errors = $this->validateCustomerData($data, $id);
        if ($errors) {
            setFlashMessage('error', implode("
", $errors));
            redirect('customers', ['action' => 'edit', 'id' => $id]);
        }

        $params = array_values($data);
        $params[] = $id;
        $this->pdo->prepare("
            UPDATE customers SET document_type = ?, document = ?, name = ?, phone = ?,
                email = ?, address = ?, city = ?, state = ?, customer_type = ?,
                credit_enabled = ?, credit_limit = ?, status = ?
            WHERE id = ?
        ")->execute($params);

        Security::auditLog('customer_updated', [
            'module' => 'customers',
            'record_id' => (string) $id,
            'before' => $before,
            'after' => $data,
        ]);
        setFlashMessage('success', 'Cliente atualizado com sucesso.');
        redirect('customers');
    }

    public function delete(): void {
        Security::checkPermissions('manager');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('customers');
        }
        Security::validateRequest();

        $id = (int) ($_POST['id'] ?? 0);
        $customer = $this->findById($id);
        if (!$customer || (int) $customer['is_default'] === 1) {
            setFlashMessage('error', 'Cliente nao pode ser excluido.');
            redirect('customers');
        }

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM sales WHERE customer_id = ?');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            $this->pdo->prepare("UPDATE customers SET status = 'inactive' WHERE id = ?")->execute([$id]);
            Security::auditLog('customer_inactivated', [
                'module' => 'customers',
                'record_id' => (string) $id,
                'before' => $customer,
                'after' => ['status' => 'inactive'],
            ]);
            setFlashMessage('warning', 'Cliente possui historico e foi inativado.');
            redirect('customers');
        }

        $this->pdo->prepare('DELETE FROM customers WHERE id = ?')->execute([$id]);
        Security::auditLog('customer_deleted', [
            'module' => 'customers',
            'record_id' => (string) $id,
            'before' => $customer,
        ]);
        setFlashMessage('success', 'Cliente excluido com sucesso.');
        redirect('customers');
    }

    private function customerDataFromPost(): array {
        $type = $_POST['document_type'] ?? 'cpf';
        if (!in_array($type, ['cpf', 'cnpj', 'none'], true)) {
            $type = 'cpf';
        }
        $document = $type === 'none' ? null : onlyDigits($_POST['document'] ?? '');
        return [
            'document_type' => $type,
            'document' => $document,
            'name' => sanitize($_POST['name'] ?? ''),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'email' => strtolower(trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL))),
            'address' => sanitize($_POST['address'] ?? ''),
            'city' => sanitize($_POST['city'] ?? ''),
            'state' => strtoupper(substr(sanitize($_POST['state'] ?? ''), 0, 2)),
            'customer_type' => array_key_exists($_POST['customer_type'] ?? '', CUSTOMER_TYPES) ? $_POST['customer_type'] : 'retail',
            'credit_enabled' => isset($_POST['credit_enabled']) ? 1 : 0,
            'credit_limit' => normalizeMoney($_POST['credit_limit'] ?? 0),
            'status' => in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : 'active',
        ];
    }

    private function validateCustomerData(array $data, ?int $ignoreId = null): array {
        $errors = [];
        if ($data['name'] === '') {
            $errors[] = 'Nome do cliente e obrigatorio.';
        }
        if ($data['document_type'] !== 'none' && !isValidCpfCnpj((string) $data['document'], $data['document_type'])) {
            $errors[] = 'Documento invalido.';
        }
        if ($data['email'] !== '' && !isValidEmail($data['email'])) {
            $errors[] = 'Email invalido.';
        }
        if ($data['document']) {
            $sql = 'SELECT id FROM customers WHERE document = ?';
            $params = [$data['document']];
            if ($ignoreId) {
                $sql .= ' AND id <> ?';
                $params[] = $ignoreId;
            }
            $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
            $stmt->execute($params);
            if ($stmt->fetch()) {
                $errors[] = 'Ja existe cliente com este documento.';
            }
        }
        return $errors;
    }

    private function findById(int $id): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM customers WHERE id = ?');
        $stmt->execute([$id]);
        $customer = $stmt->fetch();
        return $customer ?: null;
    }
}
