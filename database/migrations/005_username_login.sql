-- Migration 005 - Login por usuario ou email
-- Em ambientes novos, prefira importar schema.sql completo.
-- Selecione o banco desejado antes de executar esta migracao.

DROP PROCEDURE IF EXISTS fs_exec_if_column_missing;
DROP PROCEDURE IF EXISTS fs_exec_if_index_missing;

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
DELIMITER ;

CALL fs_exec_if_column_missing('users', 'username', 'ALTER TABLE `users` ADD COLUMN `username` VARCHAR(80) NULL DEFAULT NULL AFTER `name`');
CALL fs_exec_if_index_missing('users', 'uq_users_username', 'ALTER TABLE `users` ADD UNIQUE KEY `uq_users_username` (`username`)');

UPDATE `users`
SET `username` = 'FerragensSouza',
    `password_hash` = '$2y$12$PTBMS8nDeXm5ucjU1h1UW.tAMyNCJnRlLZJ/joUNr3S5LYBDRbeky',
    `must_change_password` = 0,
    `status` = 'active'
WHERE `email` = 'admin@ferragensouza.local'
LIMIT 1;

DROP PROCEDURE IF EXISTS fs_exec_if_column_missing;
DROP PROCEDURE IF EXISTS fs_exec_if_index_missing;
