-- ============================================================
-- Ferragens Souza - Schema do Banco de Dados
-- MySQL 8.0+ / MariaDB 10.6+
--
-- Como usar:
--   CREATE DATABASE ferragens_souza CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   mysql -u root -p ferragens_souza < schema.sql
-- Ou selecione o banco desejado no phpMyAdmin antes de importar.
-- ============================================================

CREATE DATABASE IF NOT EXISTS `ferragens_souza` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ferragens_souza`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Usuarios do sistema e hierarquia de acesso.
CREATE TABLE IF NOT EXISTS `users` (
    `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`                 VARCHAR(150) NOT NULL,
    `username`             VARCHAR(80) NULL DEFAULT NULL,
    `email`                VARCHAR(200) NOT NULL,
    `password_hash`        VARCHAR(255) NOT NULL,
    `role`                 ENUM('admin','manager','operator','seller') NOT NULL DEFAULT 'operator',
    `status`               ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
    `last_login_at`        DATETIME NULL DEFAULT NULL,
    `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_username` (`username`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role_status` (`role`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categorias e subcategorias do segmento de ferragens.
CREATE TABLE IF NOT EXISTS `categories` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `parent_id`   INT UNSIGNED NULL DEFAULT NULL,
    `code`        VARCHAR(12) NULL DEFAULT NULL,
    `name`        VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NULL DEFAULT NULL,
    `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categories_name_parent` (`name`, `parent_id`),
    UNIQUE KEY `uq_categories_code` (`code`),
    KEY `idx_categories_parent` (`parent_id`),
    CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`)
        REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fornecedores pessoa juridica.
CREATE TABLE IF NOT EXISTS `suppliers` (
    `id`                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`                     VARCHAR(150) NOT NULL,
    `legal_name`               VARCHAR(180) NULL DEFAULT NULL,
    `trade_name`               VARCHAR(150) NULL DEFAULT NULL,
    `cnpj`                     VARCHAR(18) NULL DEFAULT NULL,
    `segment`                  VARCHAR(80) NULL DEFAULT NULL,
    `email`                    VARCHAR(200) NULL DEFAULT NULL,
    `phone`                    VARCHAR(20) NULL DEFAULT NULL,
    `address`                  VARCHAR(255) NULL DEFAULT NULL,
    `city`                     VARCHAR(100) NULL DEFAULT NULL,
    `state`                    VARCHAR(2) NULL DEFAULT NULL,
    `commercial_contact_name`  VARCHAR(120) NULL DEFAULT NULL,
    `commercial_contact_phone` VARCHAR(20) NULL DEFAULT NULL,
    `default_payment_terms`    VARCHAR(50) NULL DEFAULT NULL,
    `commercial_terms`         VARCHAR(255) NULL DEFAULT NULL,
    `credit_limit`             DECIMAL(12,2) NULL DEFAULT NULL,
    `status`                   ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`               DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_suppliers_cnpj` (`cnpj`),
    KEY `idx_suppliers_status` (`status`),
    KEY `idx_suppliers_segment` (`segment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clientes de varejo, profissionais e consumidor final fixo.
CREATE TABLE IF NOT EXISTS `customers` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `document_type`  ENUM('cpf','cnpj','none') NOT NULL DEFAULT 'none',
    `document`       VARCHAR(18) NULL DEFAULT NULL,
    `name`           VARCHAR(160) NOT NULL,
    `phone`          VARCHAR(20) NULL DEFAULT NULL,
    `email`          VARCHAR(200) NULL DEFAULT NULL,
    `address`        VARCHAR(255) NULL DEFAULT NULL,
    `city`           VARCHAR(100) NULL DEFAULT NULL,
    `state`          VARCHAR(2) NULL DEFAULT NULL,
    `customer_type`  ENUM('retail','professional') NOT NULL DEFAULT 'retail',
    `credit_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `credit_limit`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `is_default`     TINYINT(1) NOT NULL DEFAULT 0,
    `status`         ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_customers_document` (`document`),
    KEY `idx_customers_type_status` (`customer_type`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Produtos de ferragens, materiais de construcao e ferramentas.
CREATE TABLE IF NOT EXISTS `products` (
    `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sku`                   VARCHAR(50) NOT NULL,
    `name`                  VARCHAR(200) NOT NULL,
    `description`           TEXT NULL DEFAULT NULL,
    `category_id`           INT UNSIGNED NULL DEFAULT NULL,
    `subcategory_id`        INT UNSIGNED NULL DEFAULT NULL,
    `supplier_id`           INT UNSIGNED NULL DEFAULT NULL,
    `alternate_supplier_id` INT UNSIGNED NULL DEFAULT NULL,
    `brand`                 VARCHAR(100) NULL DEFAULT NULL,
    `unit_of_measure`       ENUM('UN','MT','KG','LT','PAR','CX','RL','PC') NOT NULL DEFAULT 'UN',
    `purchase_unit`         ENUM('UN','MT','KG','LT','PAR','CX','RL','PC') NOT NULL DEFAULT 'UN',
    `conversion_factor`     DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
    `cost_price`            DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `sale_price`            DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `wholesale_price`       DECIMAL(12,2) NULL DEFAULT NULL,
    `margin_percent`        DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `markup_percent`        DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `quantity`              DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    `min_quantity`          DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    `reorder_point`         DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    `status`                ENUM('active','inactive','discontinued') NOT NULL DEFAULT 'active',
    `notes`                 TEXT NULL DEFAULT NULL,
    `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_products_sku` (`sku`),
    KEY `idx_products_category` (`category_id`),
    KEY `idx_products_subcategory` (`subcategory_id`),
    KEY `idx_products_supplier` (`supplier_id`),
    KEY `idx_products_alt_supplier` (`alternate_supplier_id`),
    KEY `idx_products_status_stock` (`status`, `quantity`, `min_quantity`),
    CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`)
        REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_products_subcategory` FOREIGN KEY (`subcategory_id`)
        REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_products_supplier` FOREIGN KEY (`supplier_id`)
        REFERENCES `suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_products_alt_supplier` FOREIGN KEY (`alternate_supplier_id`)
        REFERENCES `suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_products_prices` CHECK (`cost_price` >= 0 AND `sale_price` >= 0),
    CONSTRAINT `chk_products_stock` CHECK (`min_quantity` >= 0 AND `reorder_point` >= 0),
    CONSTRAINT `chk_products_conversion` CHECK (`conversion_factor` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historico auditavel de alteracoes de custo cadastral.
CREATE TABLE IF NOT EXISTS `product_cost_history` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id`   INT UNSIGNED NOT NULL,
    `old_cost`     DECIMAL(12,2) NOT NULL,
    `new_cost`     DECIMAL(12,2) NOT NULL,
    `source_table` VARCHAR(60) NOT NULL,
    `source_id`    INT UNSIGNED NOT NULL,
    `changed_by`   INT UNSIGNED NULL DEFAULT NULL,
    `changed_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_product_cost_history_product` (`product_id`, `changed_at`),
    CONSTRAINT `fk_product_cost_history_product` FOREIGN KEY (`product_id`)
        REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_product_cost_history_user` FOREIGN KEY (`changed_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Cabecalho de entrada fiscal/manual de fornecedor.
CREATE TABLE IF NOT EXISTS `fiscal_entries` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `supplier_id`    INT UNSIGNED NOT NULL,
    `invoice_number` VARCHAR(30) NOT NULL,
    `invoice_series` VARCHAR(10) NOT NULL DEFAULT '1',
    `access_key`     VARCHAR(44) NULL DEFAULT NULL,
    `issue_date`     DATE NOT NULL,
    `total_amount`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `cst`            VARCHAR(10) NULL DEFAULT NULL,
    `icms_base`      DECIMAL(12,2) NULL DEFAULT NULL,
    `status`         ENUM('draft','confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
    `created_by`     INT UNSIGNED NULL DEFAULT NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_fiscal_entry_supplier_nf_series` (`supplier_id`, `invoice_number`, `invoice_series`),
    KEY `idx_fiscal_entries_issue_date` (`issue_date`),
    CONSTRAINT `fk_fiscal_entries_supplier` FOREIGN KEY (`supplier_id`)
        REFERENCES `suppliers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_fiscal_entries_user` FOREIGN KEY (`created_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fiscal_entry_items` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `fiscal_entry_id` INT UNSIGNED NOT NULL,
    `product_id`      INT UNSIGNED NOT NULL,
    `quantity`        DECIMAL(12,3) NOT NULL,
    `unit_cost`       DECIMAL(12,2) NOT NULL,
    `total_cost`      DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_fiscal_entry_items_entry` (`fiscal_entry_id`),
    KEY `idx_fiscal_entry_items_product` (`product_id`),
    CONSTRAINT `fk_fiscal_entry_items_entry` FOREIGN KEY (`fiscal_entry_id`)
        REFERENCES `fiscal_entries` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_fiscal_entry_items_product` FOREIGN KEY (`product_id`)
        REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Caixas por operador/turno.
CREATE TABLE IF NOT EXISTS `cash_registers` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`           INT UNSIGNED NOT NULL,
    `opened_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `closed_at`         DATETIME NULL DEFAULT NULL,
    `initial_balance`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `expected_balance`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `counted_balance`   DECIMAL(12,2) NULL DEFAULT NULL,
    `difference_amount` DECIMAL(12,2) NULL DEFAULT NULL,
    `status`            ENUM('open','closed','forced_closed') NOT NULL DEFAULT 'open',
    `closed_by`         INT UNSIGNED NULL DEFAULT NULL,
    `admin_approval_id` INT UNSIGNED NULL DEFAULT NULL,
    `close_notes`       VARCHAR(255) NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_cash_registers_user_status` (`user_id`, `status`),
    KEY `idx_cash_registers_opened_at` (`opened_at`),
    KEY `idx_cash_registers_admin_approval` (`admin_approval_id`),
    CONSTRAINT `fk_cash_registers_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_cash_registers_closed_by` FOREIGN KEY (`closed_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_cash_registers_admin_approval` FOREIGN KEY (`admin_approval_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vendas de balcao.
CREATE TABLE IF NOT EXISTS `sales` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sale_number`     VARCHAR(30) NOT NULL,
    `customer_id`     INT UNSIGNED NULL DEFAULT NULL,
    `cash_register_id` INT UNSIGNED NOT NULL,
    `user_id`         INT UNSIGNED NULL DEFAULT NULL,
    `subtotal`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total_amount`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `notes`           TEXT NULL DEFAULT NULL,
    `status`          ENUM('draft','completed','cancelled','returned') NOT NULL DEFAULT 'completed',
    `cancel_reason`   VARCHAR(255) NULL DEFAULT NULL,
    `cancelled_by`    INT UNSIGNED NULL DEFAULT NULL,
    `cancelled_at`    DATETIME NULL DEFAULT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sales_sale_number` (`sale_number`),
    KEY `idx_sales_created_status` (`created_at`, `status`),
    KEY `idx_sales_customer` (`customer_id`),
    CONSTRAINT `fk_sales_customer` FOREIGN KEY (`customer_id`)
        REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sales_cash_register` FOREIGN KEY (`cash_register_id`)
        REFERENCES `cash_registers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sales_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sales_cancelled_by` FOREIGN KEY (`cancelled_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sale_items` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sale_id`          INT UNSIGNED NOT NULL,
    `product_id`       INT UNSIGNED NOT NULL,
    `sku_snapshot`     VARCHAR(50) NOT NULL,
    `product_name`     VARCHAR(200) NOT NULL,
    `quantity`         DECIMAL(12,3) NOT NULL,
    `unit_price`       DECIMAL(12,2) NOT NULL,
    `cost_price`       DECIMAL(12,2) NOT NULL,
    `discount_percent` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `discount_amount`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `line_total`       DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_sale_items_sale` (`sale_id`),
    KEY `idx_sale_items_product` (`product_id`),
    CONSTRAINT `fk_sale_items_sale` FOREIGN KEY (`sale_id`)
        REFERENCES `sales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_sale_items_product` FOREIGN KEY (`product_id`)
        REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sale_payments` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sale_id`       INT UNSIGNED NOT NULL,
    `payment_method` ENUM('cash','debit_card','credit_card','pix','store_credit','customer_credit') NOT NULL,
    `amount`        DECIMAL(12,2) NOT NULL,
    `installments`  TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `change_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `confirmed`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sale_payments_sale` (`sale_id`),
    KEY `idx_sale_payments_method` (`payment_method`),
    CONSTRAINT `fk_sale_payments_sale` FOREIGN KEY (`sale_id`)
        REFERENCES `sales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cash_movements` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cash_register_id` INT UNSIGNED NOT NULL,
    `sale_id`          INT UNSIGNED NULL DEFAULT NULL,
    `user_id`          INT UNSIGNED NULL DEFAULT NULL,
    `type`             ENUM('opening','sale','withdrawal','supply','refund','closing_adjustment') NOT NULL,
    `payment_method`   ENUM('cash','debit_card','credit_card','pix','store_credit','customer_credit','transfer','boleto','check') NULL DEFAULT NULL,
    `amount`           DECIMAL(12,2) NOT NULL,
    `reason`           VARCHAR(255) NULL DEFAULT NULL,
    `approved_by`      INT UNSIGNED NULL DEFAULT NULL,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cash_movements_register` (`cash_register_id`, `created_at`),
    KEY `idx_cash_movements_sale` (`sale_id`),
    CONSTRAINT `fk_cash_movements_register` FOREIGN KEY (`cash_register_id`)
        REFERENCES `cash_registers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_cash_movements_sale` FOREIGN KEY (`sale_id`)
        REFERENCES `sales` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_cash_movements_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_cash_movements_approved_by` FOREIGN KEY (`approved_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Movimentacoes de estoque com rastreabilidade fiscal/venda.
CREATE TABLE IF NOT EXISTS `stock_movements` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id`      INT UNSIGNED NULL DEFAULT NULL,
    `user_id`         INT UNSIGNED NULL DEFAULT NULL,
    `type`            ENUM('entry','exit','adjustment','return','loss') NOT NULL,
    `quantity`        DECIMAL(12,3) NOT NULL,
    `old_quantity`    DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    `new_quantity`    DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    `unit_cost`       DECIMAL(12,2) NULL DEFAULT NULL,
    `reason`          VARCHAR(255) NULL DEFAULT NULL,
    `supplier_id`     INT UNSIGNED NULL DEFAULT NULL,
    `sale_id`         INT UNSIGNED NULL DEFAULT NULL,
    `sale_item_id`    INT UNSIGNED NULL DEFAULT NULL,
    `fiscal_entry_id` INT UNSIGNED NULL DEFAULT NULL,
    `invoice_number`  VARCHAR(30) NULL DEFAULT NULL,
    `invoice_series`  VARCHAR(10) NULL DEFAULT NULL,
    `is_theft_loss`   TINYINT(1) NOT NULL DEFAULT 0,
    `approved_by`     INT UNSIGNED NULL DEFAULT NULL,
    `date`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_movements_product_date` (`product_id`, `date`),
    KEY `idx_movements_user` (`user_id`),
    KEY `idx_movements_type_date` (`type`, `date`),
    KEY `idx_movements_sale` (`sale_id`),
    KEY `idx_movements_sale_item` (`sale_item_id`),
    CONSTRAINT `fk_movements_product` FOREIGN KEY (`product_id`)
        REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_movements_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_movements_supplier` FOREIGN KEY (`supplier_id`)
        REFERENCES `suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_movements_sale` FOREIGN KEY (`sale_id`)
        REFERENCES `sales` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_movements_sale_item` FOREIGN KEY (`sale_item_id`)
        REFERENCES `sale_items` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_movements_fiscal_entry` FOREIGN KEY (`fiscal_entry_id`)
        REFERENCES `fiscal_entries` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_movements_approved_by` FOREIGN KEY (`approved_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Creditos gerados por devolucoes para uso em compras futuras.
CREATE TABLE IF NOT EXISTS `customer_credits` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id`  INT UNSIGNED NOT NULL,
    `sale_id`      INT UNSIGNED NOT NULL,
    `used_sale_id` INT UNSIGNED NULL DEFAULT NULL,
    `amount`       DECIMAL(12,2) NOT NULL,
    `used_amount`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `reason`       VARCHAR(255) NOT NULL,
    `status`       ENUM('open','partial','used','cancelled') NOT NULL DEFAULT 'open',
    `created_by`   INT UNSIGNED NULL DEFAULT NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_customer_credits_customer_status` (`customer_id`, `status`),
    CONSTRAINT `fk_customer_credits_customer` FOREIGN KEY (`customer_id`)
        REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_customer_credits_sale` FOREIGN KEY (`sale_id`)
        REFERENCES `sales` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_customer_credits_used_sale` FOREIGN KEY (`used_sale_id`)
        REFERENCES `sales` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_customer_credits_user` FOREIGN KEY (`created_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quotations` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `quotation_number` VARCHAR(30) NOT NULL,
    `customer_id`     INT UNSIGNED NULL DEFAULT NULL,
    `customer_name`   VARCHAR(160) NULL DEFAULT NULL,
    `user_id`         INT UNSIGNED NULL DEFAULT NULL,
    `valid_until`     DATE NOT NULL,
    `subtotal`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total_amount`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `status`          ENUM('draft','sent','approved','rejected','expired','converted') NOT NULL DEFAULT 'draft',
    `sale_id`         INT UNSIGNED NULL DEFAULT NULL,
    `notes`           TEXT NULL DEFAULT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_quotations_number` (`quotation_number`),
    KEY `idx_quotations_valid_status` (`valid_until`, `status`),
    CONSTRAINT `fk_quotations_customer` FOREIGN KEY (`customer_id`)
        REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_quotations_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_quotations_sale` FOREIGN KEY (`sale_id`)
        REFERENCES `sales` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quotation_items` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `quotation_id`   INT UNSIGNED NOT NULL,
    `product_id`     INT UNSIGNED NOT NULL,
    `sku_snapshot`   VARCHAR(50) NOT NULL,
    `product_name`   VARCHAR(200) NOT NULL,
    `quantity`       DECIMAL(12,3) NOT NULL,
    `unit_price`     DECIMAL(12,2) NOT NULL,
    `cost_price`     DECIMAL(12,2) NOT NULL,
    `line_total`     DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_quotation_items_quotation` (`quotation_id`),
    CONSTRAINT `fk_quotation_items_quotation` FOREIGN KEY (`quotation_id`)
        REFERENCES `quotations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_quotation_items_product` FOREIGN KEY (`product_id`)
        REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `accounts_payable` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `supplier_id`     INT UNSIGNED NOT NULL,
    `fiscal_entry_id` INT UNSIGNED NULL DEFAULT NULL,
    `description`     VARCHAR(200) NOT NULL,
    `due_date`        DATE NOT NULL,
    `amount`          DECIMAL(12,2) NOT NULL,
    `paid_amount`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `payment_date`    DATE NULL DEFAULT NULL,
    `payment_method`  ENUM('pix','transfer','boleto','check','cash') NULL DEFAULT NULL,
    `status`          ENUM('open','paid','partial','cancelled') NOT NULL DEFAULT 'open',
    `notes`           VARCHAR(255) NULL DEFAULT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_accounts_payable_due_status` (`due_date`, `status`),
    KEY `idx_accounts_payable_supplier` (`supplier_id`),
    CONSTRAINT `fk_accounts_payable_supplier` FOREIGN KEY (`supplier_id`)
        REFERENCES `suppliers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_accounts_payable_fiscal_entry` FOREIGN KEY (`fiscal_entry_id`)
        REFERENCES `fiscal_entries` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `accounts_receivable` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sale_id`        INT UNSIGNED NULL DEFAULT NULL,
    `customer_id`    INT UNSIGNED NULL DEFAULT NULL,
    `description`    VARCHAR(200) NOT NULL,
    `installment_no` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `installments`   TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `due_date`       DATE NOT NULL,
    `amount`         DECIMAL(12,2) NOT NULL,
    `received_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `received_date`  DATE NULL DEFAULT NULL,
    `status`         ENUM('open','paid','partial','cancelled') NOT NULL DEFAULT 'open',
    `source`         ENUM('credit_card','store_credit') NOT NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_accounts_receivable_due_status` (`due_date`, `status`),
    KEY `idx_accounts_receivable_customer` (`customer_id`),
    CONSTRAINT `fk_accounts_receivable_sale` FOREIGN KEY (`sale_id`)
        REFERENCES `sales` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_accounts_receivable_customer` FOREIGN KEY (`customer_id`)
        REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `financial_ledger` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `source_table` VARCHAR(60) NOT NULL,
    `source_id`    INT UNSIGNED NOT NULL,
    `entry_type`   ENUM('income','expense','adjustment') NOT NULL,
    `amount`       DECIMAL(12,2) NOT NULL,
    `description`  VARCHAR(255) NOT NULL,
    `created_by`   INT UNSIGNED NULL DEFAULT NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_financial_ledger_source` (`source_table`, `source_id`),
    KEY `idx_financial_ledger_date_type` (`created_at`, `entry_type`),
    CONSTRAINT `fk_financial_ledger_user` FOREIGN KEY (`created_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Auditoria SQL imutavel pela interface.
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED NULL DEFAULT NULL,
    `user_name`    VARCHAR(150) NOT NULL,
    `module`       VARCHAR(60) NOT NULL,
    `action`       VARCHAR(100) NOT NULL,
    `record_id`    VARCHAR(60) NULL DEFAULT NULL,
    `before_data`  JSON NULL DEFAULT NULL,
    `after_data`   JSON NULL DEFAULT NULL,
    `details`      JSON NULL DEFAULT NULL,
    `ip`           VARCHAR(45) NOT NULL,
    `user_agent`   VARCHAR(300) NOT NULL,
    `created_at`   DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    KEY `idx_audit_logs_user` (`user_id`),
    KEY `idx_audit_logs_created` (`created_at`),
    KEY `idx_audit_logs_module_action` (`module`, `action`),
    CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED
-- ============================================================

INSERT IGNORE INTO `users` (`name`, `username`, `email`, `password_hash`, `role`, `status`, `must_change_password`) VALUES
    ('Administrador', 'FerragensSouza', 'admin@ferragensouza.local', '$2y$12$PTBMS8nDeXm5ucjU1h1UW.tAMyNCJnRlLZJ/joUNr3S5LYBDRbeky', 'admin', 'active', 0);

INSERT IGNORE INTO `customers` (`id`, `document_type`, `document`, `name`, `customer_type`, `is_default`, `status`) VALUES
    (1, 'none', NULL, 'Consumidor Final', 'retail', 1, 'active');

INSERT IGNORE INTO `categories` (`code`, `name`, `description`, `status`) VALUES
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
