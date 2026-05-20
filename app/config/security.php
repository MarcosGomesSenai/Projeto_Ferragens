<?php
/**
 * Servicos de seguranca: CSRF, auditoria, rate limit e permissoes.
 */

class Security {
    public static function validateRequest(): bool {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? '';
            if (!verifyCsrfToken($token)) {
                http_response_code(403);
                unset($_SESSION[CSRF_TOKEN_NAME]);
                setFlashMessage('error', 'Sessao expirada. Tente novamente.');
                redirect(isLoggedIn() ? 'dashboard' : 'login');
            }
            // M-01: Invalidar token após uso bem-sucedido (synchronizer token de uso único)
            unset($_SESSION[CSRF_TOKEN_NAME]);
        }
        return true;
    }

    public static function sanitizeData($data, string $type = 'string') {
        return match ($type) {
            'email' => filter_var($data, FILTER_SANITIZE_EMAIL),
            'int' => filter_var($data, FILTER_SANITIZE_NUMBER_INT),
            'float' => filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
            'url' => filter_var($data, FILTER_SANITIZE_URL),
            default => sanitize($data),
        };
    }

    /**
     * Registra auditoria em SQL. Se o banco antigo ainda nao tiver audit_logs,
     * usa JSON como fallback para nao derrubar a operacao principal.
     */
    public static function auditLog(string $action, array $details = []): void {
        try {
            $user = getCurrentUser();
            $userId = isset($user['id']) ? (int) $user['id'] : null;
            $userName = $user['name'] ?? 'Visitante';
            $module = $details['module'] ?? self::moduleFromAction($action);
            $recordId = isset($details['record_id']) ? (string) $details['record_id'] : null;
            $before = $details['before'] ?? null;
            $after = $details['after'] ?? null;

            global $pdo;
            if ($pdo instanceof PDO) {
                $stmt = $pdo->prepare("
                    INSERT INTO audit_logs
                        (user_id, user_name, module, action, record_id, before_data, after_data, details, ip, user_agent)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $userId,
                    $userName,
                    $module,
                    $action,
                    $recordId,
                    self::jsonOrNull($before),
                    self::jsonOrNull($after),
                    self::jsonOrNull($details),
                    self::getClientIp(),
                    substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 300),
                ]);
                return;
            }
        } catch (Throwable $e) {
            error_log('[Ferragens Souza] audit_logs SQL falhou: ' . $e->getMessage());
        }

        self::auditLogJsonFallback($action, $details);
    }

    private static function auditLogJsonFallback(string $action, array $details): void {
        try {
            $user = getCurrentUser();
            $logEntry = [
                'id' => generateId(),
                'user_id' => isset($user['id']) ? (int) $user['id'] : 0,
                'user_name' => $user['name'] ?? 'Visitante',
                'action' => $action,
                'details' => $details,
                'ip' => self::getClientIp(),
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 300),
                'timestamp' => date('Y-m-d H:i:s'),
            ];

            $logFile = DATA_PATH . '/audit/audit.json';
            $logs = DataManager::load($logFile);
            $logs[] = $logEntry;
            if (count($logs) > 2000) {
                $logs = array_slice($logs, -2000);
            }
            DataManager::save($logFile, $logs);
        } catch (Throwable $e) {
            error_log('[Ferragens Souza] audit fallback falhou: ' . $e->getMessage());
        }
    }

    private static function moduleFromAction(string $action): string {
        $parts = explode('_', $action, 2);
        return $parts[0] !== '' ? $parts[0] : 'system';
    }

    private static function jsonOrNull($data): ?string {
        if ($data === null) {
            return null;
        }
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function checkPermissions($requiredRole): bool {
        if (!hasPermission($requiredRole)) {
            setFlashMessage('error', 'Voce nao tem permissao para acessar esta area.');
            redirect('dashboard');
        }
        return true;
    }

    public static function getClientIp(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public static function generateSecureToken(int $length = 32): string {
        return bin2hex(random_bytes($length));
    }

    public static function validatePasswordStrength(string $password): array {
        $errors = [];
        if (strlen($password) < 8) {
            $errors[] = 'A senha deve ter no minimo 8 caracteres';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'A senha deve conter pelo menos uma letra maiuscula';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'A senha deve conter pelo menos uma letra minuscula';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'A senha deve conter pelo menos um numero';
        }
        return $errors;
    }

    public static function rateLimit(string $action, int $maxAttempts = 5, int $timeWindow = 300): bool {
        // Rate limit por IP via tabela audit_logs quando disponível.
        // A verificação não incrementa contador; falhas são registradas separadamente.
        $ip = self::getClientIp();

        global $pdo;
        if ($pdo instanceof PDO) {
            try {
                $since = date('Y-m-d H:i:s', time() - $timeWindow);
                $failedActions = [$action . '_failed'];
                if (!str_starts_with($action, 'auth_')) {
                    $failedActions[] = 'auth_' . $action . '_failed';
                }
                $placeholders = implode(',', array_fill(0, count($failedActions), '?'));
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM audit_logs
                    WHERE action IN ($placeholders)
                      AND ip = ?
                      AND created_at >= ?
                ");
                $stmt->execute([...$failedActions, $ip, $since]);
                $attempts = (int) $stmt->fetchColumn();
                return $attempts < $maxAttempts;
            } catch (Throwable) {
                // fallback para sessão se a tabela ainda não existir
            }
        }

        $key = 'rate_limit_' . $action;
        $data = $_SESSION[$key] ?? ['attempts' => 0, 'first_attempt' => time()];
        if (time() - (int) ($data['first_attempt'] ?? time()) > $timeWindow) {
            $_SESSION[$key] = ['attempts' => 0, 'first_attempt' => time()];
            return true;
        }
        return ((int) ($data['attempts'] ?? 0)) < $maxAttempts;
    }

    public static function recordRateLimitFailure(string $action): void {
        $key = 'rate_limit_' . $action;
        $data = $_SESSION[$key] ?? ['attempts' => 0, 'first_attempt' => time()];
        if (time() - (int) ($data['first_attempt'] ?? time()) > 300) {
            $data = ['attempts' => 0, 'first_attempt' => time()];
        }
        $data['attempts'] = ((int) ($data['attempts'] ?? 0)) + 1;
        $_SESSION[$key] = $data;
    }

    public static function resetRateLimit(string $action): void {
        unset($_SESSION['rate_limit_' . $action]);
    }

    public static function verifyCurrentPassword(string $password): bool {
        if (!isLoggedIn() || $password === '') {
            return false;
        }

        global $pdo;
        if (!$pdo instanceof PDO) {
            return false;
        }

        $stmt = $pdo->prepare("SELECT password_hash, status FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([(int) ($_SESSION['user_id'] ?? 0)]);
        $user = $stmt->fetch();

        return $user
            && $user['status'] === 'active'
            && password_verify($password, $user['password_hash']);
    }

}
