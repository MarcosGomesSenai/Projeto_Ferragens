<?php
/**
 * Funcoes auxiliares compartilhadas pelo sistema.
 */

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    return $_SESSION['user_data'] ?? null;
}

function hasPermission($requiredRoles): bool {
    if (!isLoggedIn()) {
        return false;
    }

    $user = getCurrentUser();
    $userRole = $user['role'] ?? '';
    $roleHierarchy = [
        'admin' => 4,
        'manager' => 3,
        'operator' => 2,
        'seller' => 1,
        'viewer' => 0,
    ];

    $userLevel = $roleHierarchy[$userRole] ?? -1;
    $roles = is_array($requiredRoles) ? $requiredRoles : [$requiredRoles];

    foreach ($roles as $role) {
        if ($userLevel >= ($roleHierarchy[$role] ?? -1)) {
            return true;
        }
    }
    return false;
}

function redirect(string $page, array $params = []): void {
    $url = 'index.php?page=' . urlencode($page);
    foreach ($params as $key => $value) {
        $url .= '&' . urlencode((string) $key) . '=' . urlencode((string) $value);
    }
    header('Location: ' . $url);
    exit;
}

function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return trim(strip_tags((string) $data));
}

function onlyDigits(string $value): string {
    return preg_replace('/\D+/', '', $value) ?? '';
}

function formatDate($date, string $format = 'd/m/Y H:i'): string {
    if (empty($date)) {
        return '-';
    }
    $timestamp = is_numeric($date) ? (int) $date : strtotime((string) $date);
    return $timestamp ? date($format, $timestamp) : '-';
}

if (!function_exists('formatMoney')) {
    function formatMoney(float $value): string {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }
}

if (!function_exists('formatNumber')) {
    function formatNumber($value, int $decimals = 0): string {
        return number_format((float) $value, $decimals, ',', '.');
    }
}

function formatQuantity($value): string {
    $number = (float) $value;
    $decimals = abs($number - round($number)) > 0.0001 ? 3 : 0;
    return formatNumber($number, $decimals);
}

function productStockAlertLevel(array $product): string {
    $qty = (float) ($product['quantity'] ?? 0);
    if ($qty <= CRITICAL_STOCK_THRESHOLD) {
        return 'critical';
    }
    $reorderPoint = (float) ($product['reorder_point'] ?? 0);
    if ($reorderPoint > 0 && $qty <= $reorderPoint) {
        return 'low';
    }
    $min = (float) ($product['min_quantity'] ?? 0);
    if ($min > 0 && $qty <= $min) {
        return 'low';
    }
    return 'ok';
}

function generateId(): string {
    return uniqid('', true);
}

function generateCsrfToken(): string {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verifyCsrfToken($token): bool {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], (string) $token);
}

function setFlashMessage(string $type, string $message): void {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function getFlashMessage(): ?array {
    if (!isset($_SESSION['flash_message'])) {
        return null;
    }
    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
    return $message;
}

function generateSKU(string $prefix = 'PRD'): string {
    return strtoupper($prefix) . '-' . strtoupper(substr(uniqid(), -8));
}

function calculateMargin(float $cost, float $price): float {
    if ($price <= 0) {
        return 0.0;
    }
    return (($price - $cost) / $price) * 100;
}

function calculateMarkup($cost, $price): float {
    $cost = (float) $cost;
    $price = (float) $price;
    if ($cost <= 0) {
        return 0.0;
    }
    return (($price - $cost) / $cost) * 100;
}

function calculatePriceWithMarkup($cost, $markup): float {
    return (float) $cost * (1 + ((float) $markup / 100));
}

function normalizeMoney($value): float {
    if (is_string($value)) {
        $value = trim($value);
        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }
    }
    return round((float) $value, 2);
}

function normalizeQuantity($value): float {
    if (is_string($value)) {
        $value = trim($value);
        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }
    }
    return round((float) $value, 3);
}

function isValidEmail($email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isStrongPassword($password): bool {
    return strlen((string) $password) >= 8
        && preg_match('/[A-Z]/', (string) $password)
        && preg_match('/[a-z]/', (string) $password)
        && preg_match('/[0-9]/', (string) $password);
}

function isValidCpf(string $cpf): bool {
    $cpf = onlyDigits($cpf);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    for ($t = 9; $t < 11; $t++) {
        $sum = 0;
        for ($i = 0; $i < $t; $i++) {
            $sum += (int) $cpf[$i] * (($t + 1) - $i);
        }
        $digit = ((10 * $sum) % 11) % 10;
        if ((int) $cpf[$t] !== $digit) {
            return false;
        }
    }
    return true;
}

function isValidCnpj(string $cnpj): bool {
    $cnpj = onlyDigits($cnpj);
    if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
        return false;
    }

    $weights = [
        [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
        [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
    ];

    for ($round = 0; $round < 2; $round++) {
        $sum = 0;
        $length = 12 + $round;
        for ($i = 0; $i < $length; $i++) {
            $sum += (int) $cnpj[$i] * $weights[$round][$i];
        }
        $remainder = $sum % 11;
        $digit = $remainder < 2 ? 0 : 11 - $remainder;
        if ((int) $cnpj[$length] !== $digit) {
            return false;
        }
    }
    return true;
}

function isValidCpfCnpj(string $document, string $type): bool {
    if ($type === 'cpf') {
        return isValidCpf($document);
    }
    if ($type === 'cnpj') {
        return isValidCnpj($document);
    }
    return $type === 'none';
}

function isValidEan13(string $ean): bool {
    $ean = onlyDigits($ean);
    if (strlen($ean) !== 13) {
        return false;
    }
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int) $ean[$i] * (($i % 2 === 0) ? 1 : 3);
    }
    $check = (10 - ($sum % 10)) % 10;
    return $check === (int) $ean[12];
}

function addBusinessDays(DateTimeImmutable $date, int $days): DateTimeImmutable {
    $result = $date;
    $added = 0;
    while ($added < $days) {
        $result = $result->modify('+1 day');
        if ((int) $result->format('N') < 6) {
            $added++;
        }
    }
    return $result;
}


function dbDriver(): string {
    return defined('DB_DRIVER') ? DB_DRIVER : 'mysql';
}

function dbIsSQLite(): bool {
    return dbDriver() === 'sqlite';
}

function dbNow(): string {
    return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
}

function dbToday(): string {
    return (new DateTimeImmutable('today'))->format('Y-m-d');
}

function dbMonthStart(): string {
    return (new DateTimeImmutable('first day of this month 00:00:00'))->format('Y-m-d H:i:s');
}

function dbDatePlusDays(int $days): string {
    return (new DateTimeImmutable('today'))->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
}

function selectForUpdateClause(): string {
    return dbIsSQLite() ? '' : ' FOR UPDATE';
}

/**
 * Leitura/escrita JSON legada. Mantida apenas como fallback da auditoria
 * quando a tabela audit_logs ainda nao existe no banco local.
 */
class DataManager {
    public static function init(): void {}

    public static function load($file): array {
        if (!file_exists($file)) {
            return [];
        }
        $content = file_get_contents($file);
        return json_decode($content, true) ?: [];
    }

    public static function save($file, $data): bool|int {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
