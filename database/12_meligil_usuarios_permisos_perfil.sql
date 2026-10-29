-- MeliGil CRM - Usuarios, permisos y Mi perfil
-- Compatible con MySQL 5.7
-- Ejecutar una sola vez sobre la base crmcamar_meligil antes de subir la web nueva.

SET @db := DATABASE();

-- 1) Nombre separado
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='mg_admin_users' AND COLUMN_NAME='first_name');
SET @sql := IF(@exists=0, 'ALTER TABLE mg_admin_users ADD COLUMN first_name VARCHAR(100) NULL AFTER full_name', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='mg_admin_users' AND COLUMN_NAME='last_name');
SET @sql := IF(@exists=0, 'ALTER TABLE mg_admin_users ADD COLUMN last_name VARCHAR(120) NULL AFTER first_name', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Fotografía de perfil
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='mg_admin_users' AND COLUMN_NAME='profile_photo');
SET @sql := IF(@exists=0, 'ALTER TABLE mg_admin_users ADD COLUMN profile_photo VARCHAR(500) NULL AFTER last_name', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) Homologar roles: Administrador / Usuario.
-- Primero se permite temporalmente viewer + user para migrar sin perder cuentas.
ALTER TABLE mg_admin_users
  MODIFY COLUMN role ENUM('admin','viewer','user') NOT NULL DEFAULT 'user';

UPDATE mg_admin_users SET role='user' WHERE role='viewer';

ALTER TABLE mg_admin_users
  MODIFY COLUMN role ENUM('admin','user') NOT NULL DEFAULT 'user';

-- Completar nombre/apellidos de cuentas existentes cuando estén vacíos.
UPDATE mg_admin_users
SET first_name = CASE
      WHEN first_name IS NULL OR TRIM(first_name)='' THEN SUBSTRING_INDEX(TRIM(full_name),' ',1)
      ELSE first_name
    END,
    last_name = CASE
      WHEN last_name IS NULL OR TRIM(last_name)='' THEN
        TRIM(SUBSTRING(TRIM(full_name), LENGTH(SUBSTRING_INDEX(TRIM(full_name),' ',1)) + 1))
      ELSE last_name
    END;

UPDATE mg_admin_users SET last_name='Usuario' WHERE last_name IS NULL OR TRIM(last_name)='';
UPDATE mg_admin_users SET first_name='Usuario' WHERE first_name IS NULL OR TRIM(first_name)='';

-- 4) Permisos Ver / Editar por módulo.
CREATE TABLE IF NOT EXISTS mg_user_permissions (
  user_id INT(10) UNSIGNED NOT NULL,
  module_key VARCHAR(50) NOT NULL,
  can_view TINYINT(1) NOT NULL DEFAULT 0,
  can_edit TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id,module_key),
  KEY idx_mg_permissions_module (module_key),
  CONSTRAINT fk_mg_permissions_user FOREIGN KEY (user_id) REFERENCES mg_admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Las antiguas cuentas Viewer se convirtieron a Usuario. Para no perder su acceso de consulta,
-- se les asigna Ver en todos los módulos actuales y Editar desactivado.
INSERT INTO mg_user_permissions (user_id,module_key,can_view,can_edit)
SELECT u.id,m.module_key,1,0
FROM mg_admin_users u
JOIN (
  SELECT 'dashboard' module_key UNION ALL
  SELECT 'contacts' UNION ALL
  SELECT 'products' UNION ALL
  SELECT 'orders' UNION ALL
  SELECT 'quotes' UNION ALL
  SELECT 'sale_points' UNION ALL
  SELECT 'point_queries' UNION ALL
  SELECT 'messages' UNION ALL
  SELECT 'settings'
) m
WHERE u.role='user'
ON DUPLICATE KEY UPDATE can_view=VALUES(can_view), can_edit=can_edit;

SELECT 'Migración MeliGil completada' AS resultado;
