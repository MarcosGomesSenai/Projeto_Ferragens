-- Migration 004 - Simplificacao do cadastro de produtos
-- Remove campos que nao serao usados nesta versao: codigo de barras, peso,
-- localizacao fisica na loja e CFOP fiscal.
-- Use apenas em bancos MySQL/MariaDB ja existentes. Em instalacoes novas,
-- importe somente o schema.sql completo.
-- Esta migration e idempotente: pode ser executada mais de uma vez.

DROP PROCEDURE IF EXISTS fs_exec_if_column_exists;
DROP PROCEDURE IF EXISTS fs_exec_if_index_exists;
DROP PROCEDURE IF EXISTS fs_exec_if_table_exists;

DELIMITER $$
CREATE PROCEDURE fs_exec_if_column_exists(IN p_table VARCHAR(64), IN p_column VARCHAR(64), IN p_sql TEXT)
BEGIN
    IF EXISTS (
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

CREATE PROCEDURE fs_exec_if_index_exists(IN p_table VARCHAR(64), IN p_index VARCHAR(64), IN p_sql TEXT)
BEGIN
    IF EXISTS (
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

CREATE PROCEDURE fs_exec_if_table_exists(IN p_table VARCHAR(64), IN p_sql TEXT)
BEGIN
    IF EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
    ) THEN
        SET @fs_sql = p_sql;
        PREPARE fs_stmt FROM @fs_sql;
        EXECUTE fs_stmt;
        DEALLOCATE PREPARE fs_stmt;
    END IF;
END$$
DELIMITER ;

CALL fs_exec_if_table_exists('product_locations', 'DROP TABLE `product_locations`');
CALL fs_exec_if_index_exists('products', 'uq_products_barcode', 'ALTER TABLE `products` DROP INDEX `uq_products_barcode`');
CALL fs_exec_if_column_exists('products', 'barcode', 'ALTER TABLE `products` DROP COLUMN `barcode`');
CALL fs_exec_if_column_exists('products', 'weight', 'ALTER TABLE `products` DROP COLUMN `weight`');
CALL fs_exec_if_column_exists('fiscal_entries', 'cfop', 'ALTER TABLE `fiscal_entries` DROP COLUMN `cfop`');

DROP PROCEDURE IF EXISTS fs_exec_if_column_exists;
DROP PROCEDURE IF EXISTS fs_exec_if_index_exists;
DROP PROCEDURE IF EXISTS fs_exec_if_table_exists;
