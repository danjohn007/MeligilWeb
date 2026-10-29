-- MeliGil CRM + Bot · Categorías / zonas para Puntos de venta
-- Fecha: 05/10/2026
-- Ejecutar UNA VEZ sobre la base de datos actual de MeliGil ANTES de subir la web y desplegar el bot.
-- No elimina puntos de venta existentes.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS mg_sale_point_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  description VARCHAR(190) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(id),
  UNIQUE KEY uq_mg_sale_point_category_name(name),
  KEY idx_mg_sale_point_category_active(active,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La base actual compartida no tiene category_id. Esta migración se ejecuta una sola vez.
ALTER TABLE mg_sale_points
  ADD COLUMN category_id INT UNSIGNED NULL AFTER id,
  ADD KEY idx_mg_point_category(category_id);

-- Para no dejar los puntos actuales sin categoría, se crea automáticamente una categoría
-- por cada ciudad ya registrada. Después puedes renombrarlas, dividirlas o crear nuevas desde la web.
INSERT IGNORE INTO mg_sale_point_categories(name,description,active,sort_order)
SELECT TRIM(city), CONCAT('Puntos de venta de ',TRIM(city)), 1, MIN(sort_order)
FROM mg_sale_points
WHERE city IS NOT NULL AND TRIM(city)<>''
GROUP BY TRIM(city);

UPDATE mg_sale_points p
JOIN mg_sale_point_categories c ON c.name=TRIM(p.city)
SET p.category_id=c.id
WHERE p.category_id IS NULL;

-- Verificación rápida.
SELECT c.id,c.name,c.active,COUNT(p.id) AS puntos_asignados
FROM mg_sale_point_categories c
LEFT JOIN mg_sale_points p ON p.category_id=c.id
GROUP BY c.id,c.name,c.active
ORDER BY c.sort_order,c.name,c.id;
