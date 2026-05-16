-- Migration 002 - Ferragens Souza operational modules
-- Execute depois da 001 em bancos Serenity existentes.
-- Em instalacoes novas, use o schema.sql completo.
-- Selecione o banco desejado antes de executar esta migracao.

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

CREATE TABLE IF NOT EXISTS `sales` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sale_number`      VARCHAR(30) NOT NULL,
    `customer_id`      INT UNSIGNED NULL DEFAULT NULL,
    `cash_register_id` INT UNSIGNED NOT NULL,
    `user_id`          INT UNSIGNED NULL DEFAULT NULL,
    `subtotal`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `discount_amount`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total_amount`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `notes`            TEXT NULL DEFAULT NULL,
    `status`           ENUM('draft','completed','cancelled','returned') NOT NULL DEFAULT 'completed',
    `cancel_reason`    VARCHAR(255) NULL DEFAULT NULL,
    `cancelled_by`     INT UNSIGNED NULL DEFAULT NULL,
    `cancelled_at`     DATETIME NULL DEFAULT NULL,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sale_id`        INT UNSIGNED NOT NULL,
    `payment_method` ENUM('cash','debit_card','credit_card','pix','store_credit','customer_credit') NOT NULL,
    `amount`         DECIMAL(12,2) NOT NULL,
    `installments`   TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `change_amount`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `confirmed`      TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
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

ALTER TABLE `sale_payments`
    MODIFY `payment_method` ENUM('cash','debit_card','credit_card','pix','store_credit','customer_credit') NOT NULL;

ALTER TABLE `cash_movements`
    MODIFY `payment_method` ENUM('cash','debit_card','credit_card','pix','store_credit','customer_credit','transfer','boleto','check') NULL DEFAULT NULL;

DROP PROCEDURE IF EXISTS fs_exec_if_column_missing;
DROP PROCEDURE IF EXISTS fs_exec_if_index_missing;
DROP PROCEDURE IF EXISTS fs_exec_if_constraint_missing;

DELIMITER $$
CREATE PROCEDURE fs_exec_if_column_missing(IN p_table VARCHAR(64), IN p_column VARCHAR(64), IN p_sql TEXT)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
    ) THEN
        SET @fs_sql = p_sql;
        PREPARE fs_stmt FROM @fs_sql;
        EXECUTE fs_stmt;
        DEALLOCATE PREPARE fs_stmt;
    END IF;
END$$

CREATE PROCEDURE fs_exec_if_index_missing(IN p_table VARCHAR(64), IN p_index VARCHAR(64), IN p_sql TEXT)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND INDEX_NAME = p_index
    ) THEN
        SET @fs_sql = p_sql;
        PREPARE fs_stmt FROM @fs_sql;
        EXECUTE fs_stmt;
        DEALLOCATE PREPARE fs_stmt;
    END IF;
END$$

CREATE PROCEDURE fs_exec_if_constraint_missing(IN p_table VARCHAR(64), IN p_constraint VARCHAR(64), IN p_sql TEXT)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND CONSTRAINT_NAME = p_constraint
    ) THEN
        SET @fs_sql = p_sql;
        PREPARE fs_stmt FROM @fs_sql;
        EXECUTE fs_stmt;
        DEALLOCATE PREPARE fs_stmt;
    END IF;
END$$
DELIMITER ;

ALTER TABLE `stock_movements`
    MODIFY `product_id` INT UNSIGNED NULL DEFAULT NULL,
    MODIFY `type` ENUM('entry','exit','adjustment','return','loss') NOT NULL,
    MODIFY `quantity` DECIMAL(12,3) NOT NULL;

CALL fs_exec_if_column_missing('stock_movements', 'old_quantity', 'ALTER TABLE `stock_movements` ADD COLUMN `old_quantity` DECIMAL(12,3) NOT NULL DEFAULT 0.000 AFTER `quantity`');
CALL fs_exec_if_column_missing('stock_movements', 'new_quantity', 'ALTER TABLE `stock_movements` ADD COLUMN `new_quantity` DECIMAL(12,3) NOT NULL DEFAULT 0.000 AFTER `old_quantity`');
CALL fs_exec_if_column_missing('stock_movements', 'unit_cost', 'ALTER TABLE `stock_movements` ADD COLUMN `unit_cost` DECIMAL(12,2) NULL DEFAULT NULL AFTER `new_quantity`');
CALL fs_exec_if_column_missing('stock_movements', 'supplier_id', 'ALTER TABLE `stock_movements` ADD COLUMN `supplier_id` INT UNSIGNED NULL DEFAULT NULL AFTER `reason`');
CALL fs_exec_if_column_missing('stock_movements', 'sale_id', 'ALTER TABLE `stock_movements` ADD COLUMN `sale_id` INT UNSIGNED NULL DEFAULT NULL AFTER `supplier_id`');
CALL fs_exec_if_column_missing('stock_movements', 'sale_item_id', 'ALTER TABLE `stock_movements` ADD COLUMN `sale_item_id` INT UNSIGNED NULL DEFAULT NULL AFTER `sale_id`');
CALL fs_exec_if_column_missing('stock_movements', 'fiscal_entry_id', 'ALTER TABLE `stock_movements` ADD COLUMN `fiscal_entry_id` INT UNSIGNED NULL DEFAULT NULL AFTER `sale_item_id`');
CALL fs_exec_if_column_missing('stock_movements', 'invoice_number', 'ALTER TABLE `stock_movements` ADD COLUMN `invoice_number` VARCHAR(30) NULL DEFAULT NULL AFTER `fiscal_entry_id`');
CALL fs_exec_if_column_missing('stock_movements', 'invoice_series', 'ALTER TABLE `stock_movements` ADD COLUMN `invoice_series` VARCHAR(10) NULL DEFAULT NULL AFTER `invoice_number`');
CALL fs_exec_if_column_missing('stock_movements', 'is_theft_loss', 'ALTER TABLE `stock_movements` ADD COLUMN `is_theft_loss` TINYINT(1) NOT NULL DEFAULT 0 AFTER `invoice_series`');
CALL fs_exec_if_column_missing('stock_movements', 'approved_by', 'ALTER TABLE `stock_movements` ADD COLUMN `approved_by` INT UNSIGNED NULL DEFAULT NULL AFTER `is_theft_loss`');
CALL fs_exec_if_index_missing('stock_movements', 'idx_movements_product_date', 'ALTER TABLE `stock_movements` ADD KEY `idx_movements_product_date` (`product_id`, `date`)');
CALL fs_exec_if_index_missing('stock_movements', 'idx_movements_type_date', 'ALTER TABLE `stock_movements` ADD KEY `idx_movements_type_date` (`type`, `date`)');
CALL fs_exec_if_index_missing('stock_movements', 'idx_movements_sale', 'ALTER TABLE `stock_movements` ADD KEY `idx_movements_sale` (`sale_id`)');
CALL fs_exec_if_index_missing('stock_movements', 'idx_movements_sale_item', 'ALTER TABLE `stock_movements` ADD KEY `idx_movements_sale_item` (`sale_item_id`)');
CALL fs_exec_if_constraint_missing('stock_movements', 'fk_movements_supplier', 'ALTER TABLE `stock_movements` ADD CONSTRAINT `fk_movements_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
CALL fs_exec_if_constraint_missing('stock_movements', 'fk_movements_sale', 'ALTER TABLE `stock_movements` ADD CONSTRAINT `fk_movements_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
CALL fs_exec_if_constraint_missing('stock_movements', 'fk_movements_sale_item', 'ALTER TABLE `stock_movements` ADD CONSTRAINT `fk_movements_sale_item` FOREIGN KEY (`sale_item_id`) REFERENCES `sale_items` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
CALL fs_exec_if_constraint_missing('stock_movements', 'fk_movements_fiscal_entry', 'ALTER TABLE `stock_movements` ADD CONSTRAINT `fk_movements_fiscal_entry` FOREIGN KEY (`fiscal_entry_id`) REFERENCES `fiscal_entries` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
CALL fs_exec_if_constraint_missing('stock_movements', 'fk_movements_approved_by', 'ALTER TABLE `stock_movements` ADD CONSTRAINT `fk_movements_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');

DROP PROCEDURE IF EXISTS fs_exec_if_column_missing;
DROP PROCEDURE IF EXISTS fs_exec_if_index_missing;
DROP PROCEDURE IF EXISTS fs_exec_if_constraint_missing;

CREATE TABLE IF NOT EXISTS `customer_credits` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` INT UNSIGNED NOT NULL,
    `sale_id` INT UNSIGNED NOT NULL,
    `used_sale_id` INT UNSIGNED NULL DEFAULT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `used_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `reason` VARCHAR(255) NOT NULL,
    `status` ENUM('open','partial','used','cancelled') NOT NULL DEFAULT 'open',
    `created_by` INT UNSIGNED NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
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
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `quotation_number` VARCHAR(30) NOT NULL,
    `customer_id`      INT UNSIGNED NULL DEFAULT NULL,
    `customer_name`    VARCHAR(160) NULL DEFAULT NULL,
    `user_id`          INT UNSIGNED NULL DEFAULT NULL,
    `valid_until`      DATE NOT NULL,
    `subtotal`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `discount_amount`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total_amount`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `status`           ENUM('draft','sent','approved','rejected','expired','converted') NOT NULL DEFAULT 'draft',
    `sale_id`          INT UNSIGNED NULL DEFAULT NULL,
    `notes`            TEXT NULL DEFAULT NULL,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
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
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sale_id`         INT UNSIGNED NULL DEFAULT NULL,
    `customer_id`     INT UNSIGNED NULL DEFAULT NULL,
    `description`     VARCHAR(200) NOT NULL,
    `installment_no`  TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `installments`    TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `due_date`        DATE NOT NULL,
    `amount`          DECIMAL(12,2) NOT NULL,
    `received_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `received_date`   DATE NULL DEFAULT NULL,
    `status`          ENUM('open','paid','partial','cancelled') NOT NULL DEFAULT 'open',
    `source`          ENUM('credit_card','store_credit') NOT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_accounts_receivable_due_status` (`due_date`, `status`),
    KEY `idx_accounts_receivable_customer` (`customer_id`),
    CONSTRAINT `fk_accounts_receivable_sale` FOREIGN KEY (`sale_id`)
        REFERENCES `sales` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_accounts_receivable_customer` FOREIGN KEY (`customer_id`)
        REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `financial_ledger` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `source_table`  VARCHAR(60) NOT NULL,
    `source_id`     INT UNSIGNED NOT NULL,
    `entry_type`    ENUM('income','expense','adjustment') NOT NULL,
    `amount`        DECIMAL(12,2) NOT NULL,
    `description`   VARCHAR(255) NOT NULL,
    `created_by`    INT UNSIGNED NULL DEFAULT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_financial_ledger_source` (`source_table`, `source_id`),
    KEY `idx_financial_ledger_date_type` (`created_at`, `entry_type`),
    CONSTRAINT `fk_financial_ledger_user` FOREIGN KEY (`created_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
