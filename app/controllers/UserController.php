<?php
/**
 * Controlador de usuarios.
 */

class UserController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function index(): void {
        Security::checkPermissions('admin');

        $users = $this->pdo->query("
            SELECT id, name, email, role, status, created_at, last_login_at
            FROM users
            ORDER BY name ASC
        ")->fetchAll();

        require_once APP_PATH . '/views/admin/users.php';
    }

    public function delete(): void {
        Security::checkPermissions('admin');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('users');
        }
        Security::validateRequest();

        $id = (int) ($_POST['id'] ?? 0);
        $currentUserId = (int) ($_SESSION['user_id'] ?? 0);

        if ($id <= 0) {
            setFlashMessage('error', 'Usuario invalido.');
            redirect('users');
        }

        if ($id === $currentUserId) {
            setFlashMessage('error', 'Voce nao pode inativar sua propria conta.');
            redirect('users');
        }

        $this->pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?")->execute([$id]);

        Security::auditLog('user_inactivated', [
            'module' => 'users',
            'record_id' => (string) $id,
            'after' => ['status' => 'inactive'],
        ]);

        setFlashMessage('success', 'Usuario inativado com sucesso.');
        redirect('users');
    }

    public function reactivate(): void {
        Security::checkPermissions('admin');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('users');
        }
        Security::validateRequest();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            setFlashMessage('error', 'Usuario invalido.');
            redirect('users');
        }

        $this->pdo->prepare("UPDATE users SET status = 'active', updated_at = ? WHERE id = ?")->execute([dbNow(), $id]);
        Security::auditLog('user_reactivated', [
            'module' => 'users',
            'record_id' => (string) $id,
            'after' => ['status' => 'active'],
        ]);

        setFlashMessage('success', 'Usuario reativado com sucesso.');
        redirect('users');
    }

    public function resetPassword(): void {
        Security::checkPermissions('admin');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('users');
        }
        Security::validateRequest();

        $id = (int) ($_POST['id'] ?? 0);
        $password = $_POST['password'] ?? '';
        $errors = Security::validatePasswordStrength($password);
        if ($id <= 0) {
            $errors[] = 'Usuario invalido.';
        }
        if ($errors) {
            setFlashMessage('error', implode("
", $errors));
            redirect('users');
        }

        $this->pdo->prepare('UPDATE users SET password_hash = ?, must_change_password = 1, updated_at = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), dbNow(), $id]);
        Security::auditLog('user_password_reset', [
            'module' => 'users',
            'record_id' => (string) $id,
            'after' => ['must_change_password' => 1],
        ]);

        setFlashMessage('success', 'Senha temporaria definida. O usuario devera troca-la no proximo acesso.');
        redirect('users');
    }
}
