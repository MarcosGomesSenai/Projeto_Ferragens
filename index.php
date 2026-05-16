<?php
/**
 * Front controller do sistema Ferragens Souza.
 */

define('BASE_PATH', __DIR__);
define('APP_PATH', BASE_PATH . '/app');
define('DATA_PATH', BASE_PATH . '/data');
define('PUBLIC_PATH', BASE_PATH . '/public');

require_once APP_PATH . '/config/config.php';

session_start();

require_once APP_PATH . '/config/helpers.php';
require_once APP_PATH . '/config/security.php';
require_once APP_PATH . '/config/database.php';

if (!is_dir(DATA_PATH . '/audit')) {
    mkdir(DATA_PATH . '/audit', 0755, true);
}

$cspNonce = base64_encode(random_bytes(16));

header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-" . $cspNonce . "'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self';");
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

$page = sanitize($_GET['page'] ?? 'login');
$action = sanitize($_GET['action'] ?? '');

$publicPages = ['login'];
if (!in_array($page, $publicPages, true) && !isLoggedIn()) {
    redirect('login');
}

if (isLoggedIn()) {
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        session_start();
        setFlashMessage('warning', 'Sua sessao expirou. Faca login novamente.');
        redirect('login');
    }
    $_SESSION['login_time'] = time();
    if (!empty($_SESSION['must_change_password']) && !in_array($page, ['password', 'logout'], true)) {
        redirect('password', ['action' => 'changePassword']);
    }
}

$routes = [
    'login' => ['AuthController', 'app/controllers/AuthController.php'],
    'register' => ['AuthController', 'app/controllers/AuthController.php'],
    'password' => ['AuthController', 'app/controllers/AuthController.php'],
    'logout' => ['AuthController', 'app/controllers/AuthController.php'],
    'dashboard' => ['DashboardController', 'app/controllers/DashboardController.php'],
    'products' => ['ProductController', 'app/controllers/ProductController.php'],
    'suppliers' => ['SupplierController', 'app/controllers/SupplierController.php'],
    'stock' => ['StockController', 'app/controllers/StockController.php'],
    'users' => ['UserController', 'app/controllers/UserController.php'],
    'categories' => ['CategoryController', 'app/controllers/CategoryController.php'],
    'customers' => ['CustomerController', 'app/controllers/CustomerController.php'],
    'cash' => ['CashController', 'app/controllers/CashController.php'],
    'pos' => ['PosController', 'app/controllers/PosController.php'],
    'sales' => ['SaleController', 'app/controllers/SaleController.php'],
    'financial' => ['FinancialController', 'app/controllers/FinancialController.php'],
    'quotations' => ['QuotationController', 'app/controllers/QuotationController.php'],
    'reports' => ['ReportController', 'app/controllers/ReportController.php'],
    'fiscal' => ['FiscalController', 'app/controllers/FiscalController.php'],
];

$allowedActions = [
    'login' => ['login', 'authenticate', 'index'],
    'register' => ['register', 'doRegister'],
    'password' => ['changePassword', 'savePassword'],
    'logout' => ['logout'],
    'dashboard' => ['index'],
    'products' => ['index', 'add', 'save', 'edit', 'update', 'delete', 'view', 'search'],
    'suppliers' => ['index', 'add', 'save', 'edit', 'update', 'delete'],
    'stock' => ['index', 'movements', 'reverseMovement', 'adjustment', 'saveAdjustment', 'inventory', 'lowStock'],
    'users' => ['index', 'delete', 'reactivate', 'resetPassword'],
    'categories' => ['index', 'save', 'update', 'delete'],
    'customers' => ['index', 'add', 'save', 'edit', 'update', 'delete'],
    'cash' => ['index', 'open', 'saveOpen', 'movement', 'saveMovement', 'close', 'saveClose', 'forceClose'],
    'pos' => ['index', 'searchProduct', 'checkout'],
    'sales' => ['index', 'view', 'pdf', 'cancel', 'returnItem'],
    'financial' => ['index', 'payables', 'receivables', 'payPayable', 'receiveReceivable'],
    'quotations' => ['index', 'add', 'save', 'view', 'pdf', 'status', 'reopen', 'convert'],
    'reports' => ['index', 'exportCsv', 'exportPdf'],
    'fiscal' => ['index', 'add', 'save'],
];

if (!isset($routes[$page])) {
    require_once APP_PATH . '/views/errors/404.php';
    exit;
}

[$controllerClass, $controllerFile] = $routes[$page];
require_once BASE_PATH . '/' . $controllerFile;
$controller = new $controllerClass($pdo);

$permitted = $allowedActions[$page] ?? [];
$method = $action !== '' ? $action : 'index';

if (!in_array($method, $permitted, true) || !method_exists($controller, $method)) {
    require_once APP_PATH . '/views/errors/404.php';
    exit;
}

$controller->$method();
