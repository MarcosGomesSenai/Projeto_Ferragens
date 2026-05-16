<?php
/**
 * Conexao com banco de dados.
 *
 * Em instalacoes locais sem MySQL configurado, o sistema sobe automaticamente
 * com SQLite para facilitar desenvolvimento, homologacao e smoke tests.
 */

// B-06: bloco de parsing do .env removido — já executado em config.php (ENV_LOADED)

$env = static function (string $key, string $default = ''): string {
    if (array_key_exists($key, $_ENV)) {
        return (string) $_ENV[$key];
    }

    $value = getenv($key);
    return $value !== false ? (string) $value : $default;
};

$appEnv = strtolower($env('APP_ENV', 'development'));
$envExists = file_exists(BASE_PATH . '/.env');
$requestedDriver = strtolower($env('DB_DRIVER', $envExists ? 'mysql' : 'sqlite'));

$dbHost = $env('DB_HOST', 'localhost');
$dbName = $env('DB_NAME', 'serenity');
$dbUser = $env('DB_USER', 'root');
$dbPass = $env('DB_PASS', '');
$dbCharset = 'utf8mb4';
$sqlitePath = $env('DB_SQLITE_PATH', DATA_PATH . '/database/ferragens_souza.sqlite');
$sqlitePath = preg_match('/^[A-Za-z]:[\\\\\\/]/', $sqlitePath) || str_starts_with($sqlitePath, '/')
    ? $sqlitePath
    : BASE_PATH . '/' . ltrim($sqlitePath, '/\\');

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

$sqliteHasTable = static function (PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
};

$sqliteHasColumn = static function (PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->query('PRAGMA table_info("' . str_replace('"', '""', $table) . '")');
    foreach ($stmt->fetchAll() as $info) {
        if (($info['name'] ?? '') === $column) {
            return true;
        }
    }
    return false;
};

$sqliteAddColumnIfMissing = static function (PDO $pdo, string $table, string $column, string $definition) use ($sqliteHasTable, $sqliteHasColumn): void {
    if (!$sqliteHasTable($pdo, $table) || $sqliteHasColumn($pdo, $table, $column)) {
        return;
    }

    $safeTable = str_replace('"', '""', $table);
    $safeColumn = str_replace('"', '""', $column);
    $pdo->exec('ALTER TABLE "' . $safeTable . '" ADD COLUMN "' . $safeColumn . '" ' . $definition);
};

$sqliteRebuildTableFromSchema = static function (PDO $pdo, string $schema, string $table, array $columns) use ($sqliteHasTable): void {
    if (!$sqliteHasTable($pdo, $table)) {
        return;
    }

    $stmt = $pdo->prepare("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?");
    $stmt->execute([$table]);
    $createSql = (string) $stmt->fetchColumn();
    $stmt = null;
    if ($createSql === '' || !str_contains($createSql, 'CHECK') || str_contains($createSql, 'customer_credit')) {
        return;
    }

    $safeTable = str_replace('"', '""', $table);
    $legacyTable = $safeTable . '_legacy_' . date('YmdHis');
    $quotedColumns = implode(', ', array_map(
        static fn (string $column): string => '"' . str_replace('"', '""', $column) . '"',
        $columns
    ));

    $pdo->exec('PRAGMA foreign_keys = OFF');
    try {
        $pdo->exec('ALTER TABLE "' . $safeTable . '" RENAME TO "' . $legacyTable . '"');
        $pdo->exec($schema);
        $pdo->exec('INSERT INTO "' . $safeTable . '" (' . $quotedColumns . ') SELECT ' . $quotedColumns . ' FROM "' . $legacyTable . '"');
        $pdo->exec('DROP TABLE "' . $legacyTable . '"');
    } finally {
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
};

$applySqliteMigrations = static function (PDO $pdo, string $schema) use ($sqliteAddColumnIfMissing, $sqliteRebuildTableFromSchema): void {
    $sqliteAddColumnIfMissing($pdo, 'users', 'must_change_password', 'INTEGER NOT NULL DEFAULT 0');
    $sqliteAddColumnIfMissing($pdo, 'users', 'last_login_at', 'TEXT NULL');
    $sqliteAddColumnIfMissing($pdo, 'users', 'updated_at', 'TEXT NULL');

    $sqliteAddColumnIfMissing($pdo, 'categories', 'parent_id', 'INTEGER NULL');
    $sqliteAddColumnIfMissing($pdo, 'categories', 'code', 'TEXT NULL');
    $sqliteAddColumnIfMissing($pdo, 'categories', 'updated_at', 'TEXT NULL');

    $sqliteAddColumnIfMissing($pdo, 'suppliers', 'legal_name', 'TEXT NULL');
    $sqliteAddColumnIfMissing($pdo, 'suppliers', 'trade_name', 'TEXT NULL');
    $sqliteAddColumnIfMissing($pdo, 'suppliers', 'segment', 'TEXT NULL');
    $sqliteAddColumnIfMissing($pdo, 'suppliers', 'commercial_contact_name', 'TEXT NULL');
    $sqliteAddColumnIfMissing($pdo, 'suppliers', 'commercial_contact_phone', 'TEXT NULL');
    $sqliteAddColumnIfMissing($pdo, 'suppliers', 'default_payment_terms', 'TEXT NULL');
    $sqliteAddColumnIfMissing($pdo, 'suppliers', 'commercial_terms', 'TEXT NULL');
    $sqliteAddColumnIfMissing($pdo, 'suppliers', 'credit_limit', 'REAL NULL');
    $sqliteAddColumnIfMissing($pdo, 'suppliers', 'updated_at', 'TEXT NULL');

    $sqliteAddColumnIfMissing($pdo, 'products', 'subcategory_id', 'INTEGER NULL');
    $sqliteAddColumnIfMissing($pdo, 'products', 'alternate_supplier_id', 'INTEGER NULL');
    $sqliteAddColumnIfMissing($pdo, 'products', 'brand', 'TEXT NULL');
    $sqliteAddColumnIfMissing($pdo, 'products', 'unit_of_measure', "TEXT NOT NULL DEFAULT 'UN'");
    $sqliteAddColumnIfMissing($pdo, 'products', 'purchase_unit', "TEXT NOT NULL DEFAULT 'UN'");
    $sqliteAddColumnIfMissing($pdo, 'products', 'conversion_factor', 'REAL NOT NULL DEFAULT 1.0000');
    $sqliteAddColumnIfMissing($pdo, 'products', 'wholesale_price', 'REAL NULL');
    $sqliteAddColumnIfMissing($pdo, 'products', 'margin_percent', 'REAL NOT NULL DEFAULT 0.00');
    $sqliteAddColumnIfMissing($pdo, 'products', 'markup_percent', 'REAL NOT NULL DEFAULT 0.00');
    $sqliteAddColumnIfMissing($pdo, 'products', 'reorder_point', 'REAL NOT NULL DEFAULT 0.000');
    $sqliteAddColumnIfMissing($pdo, 'products', 'notes', 'TEXT NULL');
    $sqliteAddColumnIfMissing($pdo, 'products', 'updated_at', 'TEXT NULL');

    $sqliteAddColumnIfMissing($pdo, 'stock_movements', 'old_quantity', 'REAL NOT NULL DEFAULT 0.000');
    $sqliteAddColumnIfMissing($pdo, 'stock_movements', 'new_quantity', 'REAL NOT NULL DEFAULT 0.000');
    $sqliteAddColumnIfMissing($pdo, 'stock_movements', 'unit_cost', 'REAL NULL');
    $sqliteAddColumnIfMissing($pdo, 'stock_movements', 'supplier_id', 'INTEGER NULL');
    $sqliteAddColumnIfMissing($pdo, 'stock_movements', 'sale_id', 'INTEGER NULL');
    $sqliteAddColumnIfMissing($pdo, 'stock_movements', 'sale_item_id', 'INTEGER NULL');
    $sqliteAddColumnIfMissing($pdo, 'stock_movements', 'fiscal_entry_id', 'INTEGER NULL');
    $sqliteAddColumnIfMissing($pdo, 'stock_movements', 'invoice_number', 'TEXT NULL');
    $sqliteAddColumnIfMissing($pdo, 'stock_movements', 'invoice_series', 'TEXT NULL');
    $sqliteAddColumnIfMissing($pdo, 'stock_movements', 'is_theft_loss', 'INTEGER NOT NULL DEFAULT 0');
    $sqliteAddColumnIfMissing($pdo, 'stock_movements', 'approved_by', 'INTEGER NULL');
    $sqliteAddColumnIfMissing($pdo, 'cash_registers', 'admin_approval_id', 'INTEGER NULL');
    $sqliteAddColumnIfMissing($pdo, 'sales', 'notes', 'TEXT NULL');

    $sqliteRebuildTableFromSchema($pdo, $schema, 'sale_payments', [
        'id',
        'sale_id',
        'payment_method',
        'amount',
        'installments',
        'change_amount',
        'confirmed',
        'created_at',
    ]);
    $sqliteRebuildTableFromSchema($pdo, $schema, 'cash_movements', [
        'id',
        'cash_register_id',
        'sale_id',
        'user_id',
        'type',
        'payment_method',
        'amount',
        'reason',
        'approved_by',
        'created_at',
    ]);

    $pdo->exec($schema);
};

$connectSqlite = static function (string $path, array $pdoOptions) use ($sqliteHasTable, $applySqliteMigrations): PDO {
    $directory = dirname($path);
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $pdo = new PDO('sqlite:' . $path, null, null, $pdoOptions);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA journal_mode = WAL');

    $hasUsersTable = $sqliteHasTable($pdo, 'users');

    $schemaFile = BASE_PATH . '/database/sqlite_schema.sql';
    if (!file_exists($schemaFile)) {
        throw new RuntimeException('Arquivo de schema SQLite nao encontrado.');
    }

    $schema = file_get_contents($schemaFile);
    if ($schema === false) {
        throw new RuntimeException('Nao foi possivel ler o schema SQLite.');
    }

    if (!$hasUsersTable) {
        $pdo->exec($schema);
    } else {
        $applySqliteMigrations($pdo, $schema);
    }

    return $pdo;
};

$pdo = null;
$finalDriver = $requestedDriver;

try {
    if ($requestedDriver === 'sqlite') {
        $pdo = $connectSqlite($sqlitePath, $options);
    } else {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $dbHost, $dbName, $dbCharset);
        $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    }
} catch (Throwable $exception) {
    $canFallbackToSqlite = $requestedDriver !== 'sqlite'
        && $appEnv !== 'production'
        && extension_loaded('pdo_sqlite');

    if ($canFallbackToSqlite) {
        error_log('[Ferragens Souza] MySQL indisponivel, usando SQLite local: ' . $exception->getMessage());
        $pdo = $connectSqlite($sqlitePath, $options);
        $finalDriver = 'sqlite';
    } else {
        error_log('[Ferragens Souza] Falha na conexao com o banco: ' . $exception->getMessage());
        if ($appEnv === 'development') {
            die('<pre>Erro de banco (dev): ' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>');
        }
        die('Nao foi possivel conectar ao banco de dados. Contate o administrador.');
    }
}

define('DB_DRIVER', $finalDriver);
define('DB_HOST', $dbHost);
define('DB_NAME', $dbName);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);
define('DB_CHARSET', $dbCharset);
define('DB_SQLITE_PATH', $sqlitePath);

unset(
    $appEnv,
    $canFallbackToSqlite,
    $applySqliteMigrations,
    $connectSqlite,
    $dbCharset,
    $dbHost,
    $dbName,
    $dbPass,
    $dbUser,
    $env,
    $envExists,
    $exception,
    $finalDriver,
    $options,
    $requestedDriver,
    $sqliteAddColumnIfMissing,
    $sqliteHasColumn,
    $sqliteHasTable,
    $sqlitePath
);
