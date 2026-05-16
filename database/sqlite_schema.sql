PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'operator' CHECK (role IN ('admin', 'manager', 'operator', 'seller')),
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    must_change_password INTEGER NOT NULL DEFAULT 0,
    last_login_at TEXT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NULL
);

CREATE INDEX IF NOT EXISTS idx_users_role_status ON users(role, status);

CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    parent_id INTEGER NULL,
    code TEXT NULL UNIQUE,
    name TEXT NOT NULL,
    description TEXT NULL,
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NULL,
    UNIQUE(name, parent_id),
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_categories_parent ON categories(parent_id);

CREATE TABLE IF NOT EXISTS suppliers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    legal_name TEXT NULL,
    trade_name TEXT NULL,
    cnpj TEXT NULL UNIQUE,
    segment TEXT NULL,
    email TEXT NULL,
    phone TEXT NULL,
    address TEXT NULL,
    city TEXT NULL,
    state TEXT NULL,
    commercial_contact_name TEXT NULL,
    commercial_contact_phone TEXT NULL,
    default_payment_terms TEXT NULL,
    commercial_terms TEXT NULL,
    credit_limit NUMERIC NULL,
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NULL
);

CREATE INDEX IF NOT EXISTS idx_suppliers_status ON suppliers(status);
CREATE INDEX IF NOT EXISTS idx_suppliers_segment ON suppliers(segment);

CREATE TABLE IF NOT EXISTS customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    document_type TEXT NOT NULL DEFAULT 'none' CHECK (document_type IN ('cpf', 'cnpj', 'none')),
    document TEXT NULL UNIQUE,
    name TEXT NOT NULL,
    phone TEXT NULL,
    email TEXT NULL,
    address TEXT NULL,
    city TEXT NULL,
    state TEXT NULL,
    customer_type TEXT NOT NULL DEFAULT 'retail' CHECK (customer_type IN ('retail', 'professional')),
    credit_enabled INTEGER NOT NULL DEFAULT 0,
    credit_limit NUMERIC NOT NULL DEFAULT 0,
    is_default INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NULL
);

CREATE INDEX IF NOT EXISTS idx_customers_type_status ON customers(customer_type, status);

CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sku TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    description TEXT NULL,
    category_id INTEGER NULL,
    subcategory_id INTEGER NULL,
    supplier_id INTEGER NULL,
    alternate_supplier_id INTEGER NULL,
    brand TEXT NULL,
    unit_of_measure TEXT NOT NULL DEFAULT 'UN',
    purchase_unit TEXT NOT NULL DEFAULT 'UN',
    conversion_factor NUMERIC NOT NULL DEFAULT 1,
    cost_price NUMERIC NOT NULL DEFAULT 0,
    sale_price NUMERIC NOT NULL DEFAULT 0,
    wholesale_price NUMERIC NULL,
    margin_percent NUMERIC NOT NULL DEFAULT 0,
    markup_percent NUMERIC NOT NULL DEFAULT 0,
    quantity NUMERIC NOT NULL DEFAULT 0,
    min_quantity NUMERIC NOT NULL DEFAULT 0,
    reorder_point NUMERIC NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'discontinued')),
    notes TEXT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (subcategory_id) REFERENCES categories(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (alternate_supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_products_category ON products(category_id);
CREATE INDEX IF NOT EXISTS idx_products_subcategory ON products(subcategory_id);
CREATE INDEX IF NOT EXISTS idx_products_supplier ON products(supplier_id);
CREATE INDEX IF NOT EXISTS idx_products_status_stock ON products(status, quantity, min_quantity);

CREATE TABLE IF NOT EXISTS product_cost_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id INTEGER NOT NULL,
    old_cost NUMERIC NOT NULL,
    new_cost NUMERIC NOT NULL,
    source_table TEXT NOT NULL,
    source_id INTEGER NOT NULL,
    changed_by INTEGER NULL,
    changed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_product_cost_history_product ON product_cost_history(product_id, changed_at);


CREATE TABLE IF NOT EXISTS fiscal_entries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    supplier_id INTEGER NOT NULL,
    invoice_number TEXT NOT NULL,
    invoice_series TEXT NOT NULL DEFAULT '1',
    access_key TEXT NULL,
    issue_date TEXT NOT NULL,
    total_amount NUMERIC NOT NULL DEFAULT 0,
    cst TEXT NULL,
    icms_base NUMERIC NULL,
    status TEXT NOT NULL DEFAULT 'confirmed' CHECK (status IN ('draft', 'confirmed', 'cancelled')),
    created_by INTEGER NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(supplier_id, invoice_number, invoice_series),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_fiscal_entries_issue_date ON fiscal_entries(issue_date);

CREATE TABLE IF NOT EXISTS fiscal_entry_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    fiscal_entry_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    quantity NUMERIC NOT NULL,
    unit_cost NUMERIC NOT NULL,
    total_cost NUMERIC NOT NULL,
    FOREIGN KEY (fiscal_entry_id) REFERENCES fiscal_entries(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_fiscal_entry_items_entry ON fiscal_entry_items(fiscal_entry_id);
CREATE INDEX IF NOT EXISTS idx_fiscal_entry_items_product ON fiscal_entry_items(product_id);

CREATE TABLE IF NOT EXISTS cash_registers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    opened_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at TEXT NULL,
    initial_balance NUMERIC NOT NULL DEFAULT 0,
    expected_balance NUMERIC NOT NULL DEFAULT 0,
    counted_balance NUMERIC NULL,
    difference_amount NUMERIC NULL,
    status TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'closed', 'forced_closed')),
    closed_by INTEGER NULL,
    admin_approval_id INTEGER NULL,
    close_notes TEXT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (admin_approval_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_cash_registers_user_status ON cash_registers(user_id, status);
CREATE INDEX IF NOT EXISTS idx_cash_registers_opened_at ON cash_registers(opened_at);
CREATE INDEX IF NOT EXISTS idx_cash_registers_admin_approval ON cash_registers(admin_approval_id);

CREATE TABLE IF NOT EXISTS sales (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sale_number TEXT NOT NULL UNIQUE,
    customer_id INTEGER NULL,
    cash_register_id INTEGER NOT NULL,
    user_id INTEGER NULL,
    subtotal NUMERIC NOT NULL DEFAULT 0,
    discount_amount NUMERIC NOT NULL DEFAULT 0,
    total_amount NUMERIC NOT NULL DEFAULT 0,
    notes TEXT NULL,
    status TEXT NOT NULL DEFAULT 'completed' CHECK (status IN ('draft', 'completed', 'cancelled', 'returned')),
    cancel_reason TEXT NULL,
    cancelled_by INTEGER NULL,
    cancelled_at TEXT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (cash_register_id) REFERENCES cash_registers(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_sales_created_status ON sales(created_at, status);
CREATE INDEX IF NOT EXISTS idx_sales_customer ON sales(customer_id);

CREATE TABLE IF NOT EXISTS sale_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sale_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    sku_snapshot TEXT NOT NULL,
    product_name TEXT NOT NULL,
    quantity NUMERIC NOT NULL,
    unit_price NUMERIC NOT NULL,
    cost_price NUMERIC NOT NULL,
    discount_percent NUMERIC NOT NULL DEFAULT 0,
    discount_amount NUMERIC NOT NULL DEFAULT 0,
    line_total NUMERIC NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_sale_items_sale ON sale_items(sale_id);
CREATE INDEX IF NOT EXISTS idx_sale_items_product ON sale_items(product_id);

CREATE TABLE IF NOT EXISTS sale_payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sale_id INTEGER NOT NULL,
    payment_method TEXT NOT NULL CHECK (payment_method IN ('cash', 'debit_card', 'credit_card', 'pix', 'store_credit', 'customer_credit')),
    amount NUMERIC NOT NULL,
    installments INTEGER NOT NULL DEFAULT 1,
    change_amount NUMERIC NOT NULL DEFAULT 0,
    confirmed INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_sale_payments_sale ON sale_payments(sale_id);
CREATE INDEX IF NOT EXISTS idx_sale_payments_method ON sale_payments(payment_method);

CREATE TABLE IF NOT EXISTS cash_movements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    cash_register_id INTEGER NOT NULL,
    sale_id INTEGER NULL,
    user_id INTEGER NULL,
    type TEXT NOT NULL CHECK (type IN ('opening', 'sale', 'withdrawal', 'supply', 'refund', 'closing_adjustment')),
    payment_method TEXT NULL,
    amount NUMERIC NOT NULL,
    reason TEXT NULL,
    approved_by INTEGER NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cash_register_id) REFERENCES cash_registers(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_cash_movements_register ON cash_movements(cash_register_id, created_at);
CREATE INDEX IF NOT EXISTS idx_cash_movements_sale ON cash_movements(sale_id);

CREATE TABLE IF NOT EXISTS stock_movements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id INTEGER NULL,
    user_id INTEGER NULL,
    type TEXT NOT NULL CHECK (type IN ('entry', 'exit', 'adjustment', 'return', 'loss')),
    quantity NUMERIC NOT NULL,
    old_quantity NUMERIC NOT NULL DEFAULT 0,
    new_quantity NUMERIC NOT NULL DEFAULT 0,
    unit_cost NUMERIC NULL,
    reason TEXT NULL,
    supplier_id INTEGER NULL,
    sale_id INTEGER NULL,
    sale_item_id INTEGER NULL,
    fiscal_entry_id INTEGER NULL,
    invoice_number TEXT NULL,
    invoice_series TEXT NULL,
    is_theft_loss INTEGER NOT NULL DEFAULT 0,
    approved_by INTEGER NULL,
    date TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (sale_item_id) REFERENCES sale_items(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (fiscal_entry_id) REFERENCES fiscal_entries(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_movements_product_date ON stock_movements(product_id, date);
CREATE INDEX IF NOT EXISTS idx_movements_type_date ON stock_movements(type, date);
CREATE INDEX IF NOT EXISTS idx_movements_sale ON stock_movements(sale_id);
CREATE INDEX IF NOT EXISTS idx_movements_sale_item ON stock_movements(sale_item_id);

CREATE TABLE IF NOT EXISTS customer_credits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    sale_id INTEGER NOT NULL,
    used_sale_id INTEGER NULL,
    amount NUMERIC NOT NULL,
    used_amount NUMERIC NOT NULL DEFAULT 0,
    reason TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'partial', 'used', 'cancelled')),
    created_by INTEGER NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (used_sale_id) REFERENCES sales(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_customer_credits_customer_status ON customer_credits(customer_id, status);

CREATE TABLE IF NOT EXISTS quotations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    quotation_number TEXT NOT NULL UNIQUE,
    customer_id INTEGER NULL,
    customer_name TEXT NULL,
    user_id INTEGER NULL,
    valid_until TEXT NOT NULL,
    subtotal NUMERIC NOT NULL DEFAULT 0,
    discount_amount NUMERIC NOT NULL DEFAULT 0,
    total_amount NUMERIC NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'sent', 'approved', 'rejected', 'expired', 'converted')),
    sale_id INTEGER NULL,
    notes TEXT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_quotations_valid_status ON quotations(valid_until, status);

CREATE TABLE IF NOT EXISTS quotation_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    quotation_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    sku_snapshot TEXT NOT NULL,
    product_name TEXT NOT NULL,
    quantity NUMERIC NOT NULL,
    unit_price NUMERIC NOT NULL,
    cost_price NUMERIC NOT NULL,
    line_total NUMERIC NOT NULL,
    FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_quotation_items_quotation ON quotation_items(quotation_id);

CREATE TABLE IF NOT EXISTS accounts_payable (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    supplier_id INTEGER NOT NULL,
    fiscal_entry_id INTEGER NULL,
    description TEXT NOT NULL,
    due_date TEXT NOT NULL,
    amount NUMERIC NOT NULL,
    paid_amount NUMERIC NOT NULL DEFAULT 0,
    payment_date TEXT NULL,
    payment_method TEXT NULL,
    status TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'paid', 'partial', 'cancelled')),
    notes TEXT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (fiscal_entry_id) REFERENCES fiscal_entries(id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_accounts_payable_due_status ON accounts_payable(due_date, status);
CREATE INDEX IF NOT EXISTS idx_accounts_payable_supplier ON accounts_payable(supplier_id);

CREATE TABLE IF NOT EXISTS accounts_receivable (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sale_id INTEGER NULL,
    customer_id INTEGER NULL,
    description TEXT NOT NULL,
    installment_no INTEGER NOT NULL DEFAULT 1,
    installments INTEGER NOT NULL DEFAULT 1,
    due_date TEXT NOT NULL,
    amount NUMERIC NOT NULL,
    received_amount NUMERIC NOT NULL DEFAULT 0,
    received_date TEXT NULL,
    status TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'paid', 'partial', 'cancelled')),
    source TEXT NOT NULL CHECK (source IN ('credit_card', 'store_credit')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_accounts_receivable_due_status ON accounts_receivable(due_date, status);
CREATE INDEX IF NOT EXISTS idx_accounts_receivable_customer ON accounts_receivable(customer_id);

CREATE TABLE IF NOT EXISTS financial_ledger (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    source_table TEXT NOT NULL,
    source_id INTEGER NOT NULL,
    entry_type TEXT NOT NULL CHECK (entry_type IN ('income', 'expense', 'adjustment')),
    amount NUMERIC NOT NULL,
    description TEXT NOT NULL,
    created_by INTEGER NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_financial_ledger_source ON financial_ledger(source_table, source_id);
CREATE INDEX IF NOT EXISTS idx_financial_ledger_date_type ON financial_ledger(created_at, entry_type);

CREATE TABLE IF NOT EXISTS audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NULL,
    user_name TEXT NOT NULL,
    module TEXT NOT NULL,
    action TEXT NOT NULL,
    record_id TEXT NULL,
    before_data TEXT NULL,
    after_data TEXT NULL,
    details TEXT NULL,
    ip TEXT NOT NULL,
    user_agent TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_audit_logs_user ON audit_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_created ON audit_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_audit_logs_module_action ON audit_logs(module, action);

INSERT OR IGNORE INTO users (id, name, email, password_hash, role, status, must_change_password)
VALUES (1, 'Administrador', 'admin@ferragensouza.local', '$2y$12$mCuxSpYKTVov0JHfS/.CdOl8n.RnXN5aIkfqy8PITSI/b/ynSkkxO', 'admin', 'active', 1);

INSERT OR IGNORE INTO customers (id, document_type, document, name, customer_type, is_default, status)
VALUES (1, 'none', NULL, 'Consumidor Final', 'retail', 1, 'active');

INSERT OR IGNORE INTO categories (code, name, description, status) VALUES
('FG', 'Ferragens Gerais', 'Ferragens diversas para construcao e reparos', 'active'),
('FIX', 'Fixadores', 'Parafusos, buchas, pregos, porcas e arruelas', 'active'),
('FM', 'Ferramentas Manuais', 'Chaves, alicates, martelos e serras manuais', 'active'),
('FE', 'Ferramentas Eletricas', 'Furadeiras, esmerilhadeiras e ferramentas eletricas', 'active'),
('TV', 'Tintas e Vernizes', 'Tintas, vernizes, solventes e acessorios', 'active'),
('HID', 'Hidraulica', 'Tubos, conexoes, registros e acessorios hidraulicos', 'active'),
('ELE', 'Eletrica', 'Fios, cabos, tomadas, disjuntores e componentes', 'active'),
('EPI', 'EPI', 'Equipamentos de protecao individual', 'active'),
('ACB', 'Acabamentos', 'Itens de acabamento e instalacao', 'active'),
('MP', 'Madeiras e Perfis', 'Madeiras, perfis de aluminio e trilhos', 'active'),
('ADS', 'Adesivos e Silicone', 'Colas, selantes, silicones e massas', 'active'),
('PC', 'Pecas e Componentes', 'Pecas e componentes de reposicao', 'active');
