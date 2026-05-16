-- Migration 001 - Ferragens Souza core
-- Use em instalacoes existentes do Serenity antes de rodar os novos modulos.
-- Em ambientes novos, prefira importar schema.sql completo.
-- Selecione o banco desejado antes de executar esta migracao.

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

ALTER TABLE `users`
    MODIFY `role` ENUM('admin','manager','operator','seller') NOT NULL DEFAULT 'operator';
CALL fs_exec_if_column_missing('users', 'must_change_password', 'ALTER TABLE `users` ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`');
CALL fs_exec_if_column_missing('users', 'last_login_at', 'ALTER TABLE `users` ADD COLUMN `last_login_at` DATETIME NULL DEFAULT NULL AFTER `must_change_password`');
CALL fs_exec_if_column_missing('users', 'updated_at', 'ALTER TABLE `users` ADD COLUMN `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`');

CALL fs_exec_if_column_missing('categories', 'parent_id', 'ALTER TABLE `categories` ADD COLUMN `parent_id` INT UNSIGNED NULL DEFAULT NULL AFTER `id`');
CALL fs_exec_if_column_missing('categories', 'code', 'ALTER TABLE `categories` ADD COLUMN `code` VARCHAR(12) NULL DEFAULT NULL AFTER `parent_id`');
CALL fs_exec_if_column_missing('categories', 'updated_at', 'ALTER TABLE `categories` ADD COLUMN `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`');
CALL fs_exec_if_index_missing('categories', 'uq_categories_code', 'ALTER TABLE `categories` ADD UNIQUE KEY `uq_categories_code` (`code`)');
CALL fs_exec_if_index_missing('categories', 'idx_categories_parent', 'ALTER TABLE `categories` ADD KEY `idx_categories_parent` (`parent_id`)');
CALL fs_exec_if_constraint_missing('categories', 'fk_categories_parent', 'ALTER TABLE `categories` ADD CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE');

CALL fs_exec_if_column_missing('suppliers', 'legal_name', 'ALTER TABLE `suppliers` ADD COLUMN `legal_name` VARCHAR(180) NULL DEFAULT NULL AFTER `name`');
CALL fs_exec_if_column_missing('suppliers', 'trade_name', 'ALTER TABLE `suppliers` ADD COLUMN `trade_name` VARCHAR(150) NULL DEFAULT NULL AFTER `legal_name`');
CALL fs_exec_if_column_missing('suppliers', 'segment', 'ALTER TABLE `suppliers` ADD COLUMN `segment` VARCHAR(80) NULL DEFAULT NULL AFTER `cnpj`');
CALL fs_exec_if_column_missing('suppliers', 'commercial_contact_name', 'ALTER TABLE `suppliers` ADD COLUMN `commercial_contact_name` VARCHAR(120) NULL DEFAULT NULL AFTER `state`');
CALL fs_exec_if_column_missing('suppliers', 'commercial_contact_phone', 'ALTER TABLE `suppliers` ADD COLUMN `commercial_contact_phone` VARCHAR(20) NULL DEFAULT NULL AFTER `commercial_contact_name`');
CALL fs_exec_if_column_missing('suppliers', 'default_payment_terms', 'ALTER TABLE `suppliers` ADD COLUMN `default_payment_terms` VARCHAR(50) NULL DEFAULT NULL AFTER `commercial_contact_phone`');
CALL fs_exec_if_column_missing('suppliers', 'commercial_terms', 'ALTER TABLE `suppliers` ADD COLUMN `commercial_terms` VARCHAR(255) NULL DEFAULT NULL AFTER `default_payment_terms`');
CALL fs_exec_if_column_missing('suppliers', 'credit_limit', 'ALTER TABLE `suppliers` ADD COLUMN `credit_limit` DECIMAL(12,2) NULL DEFAULT NULL AFTER `commercial_terms`');
CALL fs_exec_if_column_missing('suppliers', 'updated_at', 'ALTER TABLE `suppliers` ADD COLUMN `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`');
CALL fs_exec_if_index_missing('suppliers', 'uq_suppliers_cnpj', 'ALTER TABLE `suppliers` ADD UNIQUE KEY `uq_suppliers_cnpj` (`cnpj`)');
CALL fs_exec_if_index_missing('suppliers', 'idx_suppliers_status', 'ALTER TABLE `suppliers` ADD KEY `idx_suppliers_status` (`status`)');

CALL fs_exec_if_column_missing('products', 'subcategory_id', 'ALTER TABLE `products` ADD COLUMN `subcategory_id` INT UNSIGNED NULL DEFAULT NULL AFTER `category_id`');
CALL fs_exec_if_column_missing('products', 'alternate_supplier_id', 'ALTER TABLE `products` ADD COLUMN `alternate_supplier_id` INT UNSIGNED NULL DEFAULT NULL AFTER `supplier_id`');
CALL fs_exec_if_column_missing('products', 'brand', 'ALTER TABLE `products` ADD COLUMN `brand` VARCHAR(100) NULL DEFAULT NULL AFTER `alternate_supplier_id`');
CALL fs_exec_if_column_missing('products', 'unit_of_measure', 'ALTER TABLE `products` ADD COLUMN `unit_of_measure` ENUM(''UN'',''MT'',''KG'',''LT'',''PAR'',''CX'',''RL'',''PC'') NOT NULL DEFAULT ''UN'' AFTER `brand`');
CALL fs_exec_if_column_missing('products', 'purchase_unit', 'ALTER TABLE `products` ADD COLUMN `purchase_unit` ENUM(''UN'',''MT'',''KG'',''LT'',''PAR'',''CX'',''RL'',''PC'') NOT NULL DEFAULT ''UN'' AFTER `unit_of_measure`');
CALL fs_exec_if_column_missing('products', 'conversion_factor', 'ALTER TABLE `products` ADD COLUMN `conversion_factor` DECIMAL(12,4) NOT NULL DEFAULT 1.0000 AFTER `purchase_unit`');
CALL fs_exec_if_column_missing('products', 'wholesale_price', 'ALTER TABLE `products` ADD COLUMN `wholesale_price` DECIMAL(12,2) NULL DEFAULT NULL AFTER `sale_price`');
CALL fs_exec_if_column_missing('products', 'margin_percent', 'ALTER TABLE `products` ADD COLUMN `margin_percent` DECIMAL(8,2) NOT NULL DEFAULT 0.00 AFTER `wholesale_price`');
CALL fs_exec_if_column_missing('products', 'markup_percent', 'ALTER TABLE `products` ADD COLUMN `markup_percent` DECIMAL(8,2) NOT NULL DEFAULT 0.00 AFTER `margin_percent`');
ALTER TABLE `products`
    MODIFY `quantity` DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    MODIFY `min_quantity` DECIMAL(12,3) NOT NULL DEFAULT 0.000;
CALL fs_exec_if_column_missing('products', 'reorder_point', 'ALTER TABLE `products` ADD COLUMN `reorder_point` DECIMAL(12,3) NOT NULL DEFAULT 0.000 AFTER `min_quantity`');
CALL fs_exec_if_column_missing('products', 'notes', 'ALTER TABLE `products` ADD COLUMN `notes` TEXT NULL DEFAULT NULL AFTER `status`');
CALL fs_exec_if_column_missing('products', 'updated_at', 'ALTER TABLE `products` ADD COLUMN `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`');
CALL fs_exec_if_index_missing('products', 'idx_products_subcategory', 'ALTER TABLE `products` ADD KEY `idx_products_subcategory` (`subcategory_id`)');
CALL fs_exec_if_index_missing('products', 'idx_products_alt_supplier', 'ALTER TABLE `products` ADD KEY `idx_products_alt_supplier` (`alternate_supplier_id`)');
CALL fs_exec_if_constraint_missing('products', 'fk_products_subcategory', 'ALTER TABLE `products` ADD CONSTRAINT `fk_products_subcategory` FOREIGN KEY (`subcategory_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
CALL fs_exec_if_constraint_missing('products', 'fk_products_alt_supplier', 'ALTER TABLE `products` ADD CONSTRAINT `fk_products_alt_supplier` FOREIGN KEY (`alternate_supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');

CREATE TABLE IF NOT EXISTS `product_cost_history` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `old_cost` DECIMAL(12,2) NOT NULL,
    `new_cost` DECIMAL(12,2) NOT NULL,
    `source_table` VARCHAR(60) NOT NULL,
    `source_id` INT UNSIGNED NOT NULL,
    `changed_by` INT UNSIGNED NULL DEFAULT NULL,
    `changed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_product_cost_history_product` (`product_id`, `changed_at`),
    CONSTRAINT `fk_product_cost_history_product` FOREIGN KEY (`product_id`)
        REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_product_cost_history_user` FOREIGN KEY (`changed_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `document_type` ENUM('cpf','cnpj','none') NOT NULL DEFAULT 'none',
    `document` VARCHAR(18) NULL DEFAULT NULL,
    `name` VARCHAR(160) NOT NULL,
    `phone` VARCHAR(20) NULL DEFAULT NULL,
    `email` VARCHAR(200) NULL DEFAULT NULL,
    `address` VARCHAR(255) NULL DEFAULT NULL,
    `city` VARCHAR(100) NULL DEFAULT NULL,
    `state` VARCHAR(2) NULL DEFAULT NULL,
    `customer_type` ENUM('retail','professional') NOT NULL DEFAULT 'retail',
    `credit_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `credit_limit` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_customers_document` (`document`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NULL DEFAULT NULL,
    `user_name` VARCHAR(150) NOT NULL,
    `module` VARCHAR(60) NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `record_id` VARCHAR(60) NULL DEFAULT NULL,
    `before_data` JSON NULL DEFAULT NULL,
    `after_data` JSON NULL DEFAULT NULL,
    `details` JSON NULL DEFAULT NULL,
    `ip` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(300) NOT NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    KEY `idx_audit_logs_user` (`user_id`),
    KEY `idx_audit_logs_created` (`created_at`),
    KEY `idx_audit_logs_module_action` (`module`, `action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Continue com a migracao 002 para criar PDV, caixa, fiscal,
-- financeiro, orcamentos e campos operacionais das movimentacoes.

INSERT IGNORE INTO `customers` (`id`, `document_type`, `document`, `name`, `customer_type`, `is_default`, `status`) VALUES
    (1, 'none', NULL, 'Consumidor Final', 'retail', 1, 'active');

UPDATE `users`
SET `must_change_password` = 1
WHERE `email` = 'admin@ferragensouza.local';

DROP PROCEDURE IF EXISTS fs_exec_if_column_missing;
DROP PROCEDURE IF EXISTS fs_exec_if_index_missing;
DROP PROCEDURE IF EXISTS fs_exec_if_constraint_missing;
