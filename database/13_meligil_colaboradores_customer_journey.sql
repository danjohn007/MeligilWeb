-- MeliGil CRM - Colaboradores y Customer Journey comercial
-- Compatible con MySQL 5.7
-- Ejecutar después de 12_meligil_usuarios_permisos_perfil.sql

SET NAMES utf8mb4;
SET @db := DATABASE();

-- Registrar la conversión manual a Cliente sin alterar los contactos históricos.
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='mg_contacts' AND COLUMN_NAME='client_converted_at');
SET @sql := IF(@exists=0, 'ALTER TABLE mg_contacts ADD COLUMN client_converted_at DATETIME NULL AFTER updated_at', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='mg_contacts' AND COLUMN_NAME='client_converted_by');
SET @sql := IF(@exists=0, 'ALTER TABLE mg_contacts ADD COLUMN client_converted_by INT UNSIGNED NULL AFTER client_converted_at', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='mg_contacts' AND INDEX_NAME='idx_mg_contact_client_converted');
SET @sql := IF(@idx_exists=0, 'ALTER TABLE mg_contacts ADD KEY idx_mg_contact_client_converted (client_converted_at)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'Migración 13 MeliGil completada: Customer Journey listo para conversión manual y recurrente por 3 compras.' AS resultado;
