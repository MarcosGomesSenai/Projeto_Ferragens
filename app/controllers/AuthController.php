<?php
/**
 * Controlador de autenticacao.
 *
 * Implementa login, logout, registro inicial e auditoria dos eventos de sessao.
 */

class AuthController {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function index(): void {
        $this->login();
    }

    public function login(): void {
        if (isLoggedIn()) {
            redirect('dashboard');
        }
        require_once APP_PATH . '/views/auth/login.php';
    }

    public function authenticate(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('login');
        }

        Security::validateRequest();

        if (!Security::rateLimit('login', 5, 300)) {
            setFlashMessage('error', 'Muitas tentativas de login. Tente novamente em 5 minutos.');
            redirect('login');
        }

        $email = strtolower(trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL)));
        $password = $_POST['password'] ?? '';
        if ($email === '' || $password === '') {
            setFlashMessage('error', 'Preencha email e senha.');
            redirect('login');
        }

        $stmt = $this->pdo->prepare('SELECT id, name, email, password_hash, role, status, must_change_password FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // M-04: Proteção contra timing attack — sempre executa bcrypt, mesmo sem usuário
        $dummyHash = '$2y$12$invalidhashForTimingProtectionXXXXXXXXXXXXXXXXXXXXXXXX';
        if (!$user) {
            password_verify($password, $dummyHash);
        }

        if (!$user || !password_verify($password, $user['password_hash'])) {
            Security::recordRateLimitFailure('login');
            Security::auditLog('auth_login_failed', [
                'module' => 'auth',
                'after' => ['email' => $email],
            ]);
            setFlashMessage('error', 'Email ou senha incorretos.');
            redirect('login');
        }

        if ($user['status'] !== 'active') {
            Security::auditLog('auth_login_inactive', [
                'module' => 'auth',
                'after'  => ['email' => $email, 'status' => $user['status']],
            ]);
            setFlashMessage('error', 'Conta inativa. Contate o administrador.');
            redirect('login');
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_data'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];
        $_SESSION['must_change_password'] = (int) ($user['must_change_password'] ?? 0) === 1;
        $_SESSION['login_time'] = time();
        Security::resetRateLimit('login');

        $this->pdo->prepare('UPDATE users SET last_login_at = ? WHERE id = ?')->execute([dbNow(), (int) $user['id']]);

        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        Security::auditLog('auth_login_success', [
            'module' => 'auth',
            'record_id' => (string) $user['id'],
            'after' => ['email' => $user['email'], 'role' => $user['role']],
        ]);
        setFlashMessage('success', 'Bem-vindo(a), ' . htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') . '!');
        if (!empty($_SESSION['must_change_password'])) {
            redirect('password', ['action' => 'changePassword']);
        }

        redirect($user['role'] === 'seller' ? 'pos' : 'dashboard');
    }

    public function changePassword(): void {
        Security::checkPermissions('seller');
        require_once APP_PATH . '/views/auth/change-password.php';
    }

    public function savePassword(): void {
        Security::checkPermissions('seller');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('password', ['action' => 'changePassword']);
        }
        Security::validateRequest();

        $currentPassword = $_POST['current_password'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $errors = [];

        if (!Security::verifyCurrentPassword($currentPassword)) {
            $errors[] = 'Senha atual invalida.';
        }
        if ($password !== $confirmPassword) {
            $errors[] = 'As novas senhas nao coincidem.';
        }
        $errors = array_merge($errors, Security::validatePasswordStrength($password));

        if ($errors) {
            setFlashMessage('error', implode("
", $errors));
            redirect('password', ['action' => 'changePassword']);
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $this->pdo->prepare('UPDATE users SET password_hash = ?, must_change_password = 0, updated_at = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), dbNow(), $userId]);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        if (isset($_SESSION['user_data']) && is_array($_SESSION['user_data'])) {
            $_SESSION['user_data']['id'] = $userId;
        }
        $_SESSION['must_change_password'] = false;
        $_SESSION['login_time'] = time();

        Security::auditLog('user_password_changed', [
            'module' => 'users',
            'record_id' => (string) $userId,
            'after' => ['must_change_password' => 0],
        ]);

        setFlashMessage('success', 'Senha alterada com sucesso.');
        $role = $_SESSION['user_data']['role'] ?? 'seller';
        redirect($role === 'seller' ? 'pos' : 'dashboard');
    }

    public function register(): void {
        Security::checkPermissions('admin');
        require_once APP_PATH . '/views/auth/register.php';
    }

    public function doRegister(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('login');
        }

        Security::checkPermissions('admin');
        Security::validateRequest();

        $name = sanitize($_POST['name'] ?? '');
        $email = strtolower(trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL)));
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $role = $_POST['role'] ?? 'operator';

        $errors = [];
        if ($name === '') {
            $errors[] = 'Nome e obrigatorio';
        }
        if ($email === '' || !isValidEmail($email)) {
            $errors[] = 'Email invalido';
        }
        if ($password !== $confirmPassword) {
            $errors[] = 'As senhas nao coincidem';
        }
        if (!array_key_exists($role, USER_ROLES)) {
            $errors[] = 'Perfil invalido';
        }
        $errors = array_merge($errors, Security::validatePasswordStrength($password));

        if ($errors) {
            setFlashMessage('error', implode("
", $errors));
            redirect('register');
        }

        try {
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                setFlashMessage('error', 'Este email ja esta cadastrado.');
                redirect('register');
            }

            $stmt = $this->pdo->prepare('
                INSERT INTO users (name, email, password_hash, role, status, must_change_password)
                VALUES (?, ?, ?, ?, ?, 1)
            ');
            $stmt->execute([
                $name,
                $email,
                password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                $role,
                'active',
            ]);
            $newUserId = (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log('[Ferragens Souza] Erro ao registrar usuario: ' . $e->getMessage());
            setFlashMessage('error', 'Nao foi possivel criar a conta.');
            redirect('register');
        }

        Security::auditLog('user_registered', [
            'module' => 'users',
            'record_id' => (string) $newUserId,
            'after' => ['email' => $email, 'role' => $role],
        ]);
        setFlashMessage('success', 'Usuario cadastrado com sucesso.');
        redirect('users');
    }

    public function logout(): void {
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId) {
            Security::auditLog('auth_logout', [
                'module' => 'auth',
                'record_id' => (string) $userId,
            ]);
        }

        session_unset();

        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        session_destroy();
        session_start();
        redirect('login');
    }
}
