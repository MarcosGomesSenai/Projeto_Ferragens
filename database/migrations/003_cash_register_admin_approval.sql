-- Migration 003 - Hotfixes operacionais
-- Use em bases MySQL/MariaDB existentes criadas antes desta correcao.
-- Em instalacoes novas, NAO execute esta migration: importe apenas o schema.sql completo.
-- Esta migration e idempotente: pode ser executada mais de uma vez sem duplicar coluna/indice/constraint.

DROP PROCEDURE IF EXISTS fs_exec_if_column_missing;
DROP PROCEDURE IF EXISTS fs_exec_if_index_missing;
DROP PROCEDURE IF EXISTS fs_exec_if_constraint_missing;

DELIMITER $$
CREATE PROCEDURE fs_exec_if_column_missing(IN p_table VARCHAR(64), IN p_column VARCHAR(64), IN p_sql TEXT)
BEGIN
    IF EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
    ) AND NOT EXISTS (
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
    IF EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
    ) AND NOT EXISTS (
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
    IF EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
    ) AND NOT EXISTS (
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

CALL fs_exec_if_column_missing(
    'cash_registers',
    'admin_approval_id',
    'ALTER TABLE `cash_registers` ADD COLUMN `admin_approval_id` INT UNSIGNED NULL DEFAULT NULL AFTER `closed_by`'
);
CALL fs_exec_if_index_missing(
    'cash_registers',
    'idx_cash_registers_admin_approval',
    'ALTER TABLE `cash_registers` ADD KEY `idx_cash_registers_admin_approval` (`admin_approval_id`)'
);
CALL fs_exec_if_constraint_missing(
    'cash_registers',
    'fk_cash_registers_admin_approval',
    'ALTER TABLE `cash_registers` ADD CONSTRAINT `fk_cash_registers_admin_approval` FOREIGN KEY (`admin_approval_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE'
);

CALL fs_exec_if_column_missing(
    'sales',
    'notes',
    'ALTER TABLE `sales` ADD COLUMN `notes` TEXT NULL DEFAULT NULL AFTER `total_amount`'
);

DROP PROCEDURE IF EXISTS fs_exec_if_column_missing;
DROP PROCEDURE IF EXISTS fs_exec_if_index_missing;
DROP PROCEDURE IF EXISTS fs_exec_if_constraint_missing;
