<?php
/**
 * Configuracoes gerais do sistema Ferragens Souza.
 */

if (!defined('ENV_LOADED')) {
    $envFile = BASE_PATH . '/.env';
    if (file_exists($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key = trim($key);
            $value = trim($value);

            if (
                strlen($value) >= 2
                && (($value[0] === '"' && $value[strlen($value) - 1] === '"')
                || ($value[0] === "'" && $value[strlen($value) - 1] === "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $_ENV[$key] = $value;
            if ($value !== '') {
                putenv($key . '=' . $value);
            }
        }
    }
    define('ENV_LOADED', true);
}

define('APP_NAME', 'Ferragens Souza');
define('APP_VERSION', '2.0.0 - ERP Leve');
define('APP_DESCRIPTION', 'Sistema de gestao para ferragens e materiais de construcao');
define('COMPANY_LEGAL_NAME', 'Luciano Silva Souza Comercio de Materiais para Construcao Ltda');
define('COMPANY_CNPJ', '31.806.838/0001-09');
define('COMPANY_ADDRESS', 'Rua Morro das Pedras, 95 - Loja A, Jardim Rodolfo Pirani, Sao Paulo - SP, CEP 08310-100');

date_default_timezone_set('America/Sao_Paulo');

// Sessao segura antes de session_start().
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 1 : 0);
ini_set('session.cookie_samesite', 'Lax');
$sessionPath = BASE_PATH . '/data/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0755, true);
}
session_save_path($sessionPath);
unset($sessionPath);

// Em producao, erros ficam somente no log do servidor.
$_isProduction = (getenv('APP_ENV') ?: 'development') === 'production';
error_reporting($_isProduction ? 0 : E_ALL);
ini_set('display_errors', $_isProduction ? '0' : '1');
ini_set('log_errors', '1');
unset($_isProduction);

define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_TIMEOUT', 3600);
define('ITEMS_PER_PAGE', 20);

define('MAX_FILE_SIZE', 5242880);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// Regras de negocio configuraveis.
define('LOW_STOCK_THRESHOLD', 10);
define('CRITICAL_STOCK_THRESHOLD', 5);
define('DEFAULT_QUOTATION_VALID_BUSINESS_DAYS', 5);
define('DISCOUNT_FREE_LIMIT_PERCENT', 5);
define('DISCOUNT_MANAGER_LIMIT_PERCENT', 15);
define('CASH_SHORTAGE_ADMIN_LIMIT', 50.00);

define('USER_ROLES', [
    'admin'    => 'Administrador',
    'manager'  => 'Gerente',
    'operator' => 'Operador',
    'seller'   => 'Vendedor',
]);

define('STOCK_MOVEMENT_TYPES', [
    'entry'      => 'Entrada',
    'exit'       => 'Saida',
    'adjustment' => 'Ajuste',
    'return'     => 'Devolucao',
    'loss'       => 'Perda',
]);

define('PRODUCT_STATUS', [
    'active'       => 'Ativo',
    'inactive'     => 'Inativo',
    'discontinued' => 'Descontinuado',
]);

define('PRODUCT_UNITS', [
    'UN'  => 'Unidade',
    'MT'  => 'Metro',
    'KG'  => 'Quilo',
    'LT'  => 'Litro',
    'PAR' => 'Par',
    'CX'  => 'Caixa',
    'RL'  => 'Rolo',
    'PC'  => 'Peca',
]);

define('CUSTOMER_TYPES', [
    'retail'       => 'Varejo',
    'professional' => 'Profissional/Atacado',
]);

define('PAYMENT_METHODS', [
    'cash'         => 'Dinheiro',
    'debit_card'   => 'Cartao de Debito',
    'credit_card'  => 'Cartao de Credito',
    'pix'          => 'Pix',
    'store_credit' => 'Crediario',
    'customer_credit' => 'Credito do Cliente',
]);
