SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS mg_admin_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  role ENUM('admin','viewer') NOT NULL DEFAULT 'admin',
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(id), UNIQUE KEY uq_mg_admin_email(email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mg_contacts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  wa_id VARCHAR(32) NOT NULL,
  phone VARCHAR(32) NOT NULL,
  profile_name VARCHAR(150) NULL,
  full_name VARCHAR(180) NULL,
  email VARCHAR(190) NULL,
  company VARCHAR(190) NULL,
  city VARCHAR(150) NULL,
  source VARCHAR(80) NOT NULL DEFAULT 'whatsapp',
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_inbound_at DATETIME NULL,
  last_outbound_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(id), UNIQUE KEY uq_mg_contact_wa(wa_id), KEY idx_mg_contact_phone(phone), KEY idx_mg_contact_name(full_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mg_conversations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  contact_id BIGINT UNSIGNED NOT NULL,
  current_step VARCHAR(80) NOT NULL DEFAULT 'MENU',
  flow_data_json LONGTEXT NULL,
  status ENUM('active','closed') NOT NULL DEFAULT 'active',
  last_message_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(id), UNIQUE KEY uq_mg_conv_contact(contact_id),
  CONSTRAINT fk_mg_conv_contact FOREIGN KEY(contact_id) REFERENCES mg_contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mg_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversation_id BIGINT UNSIGNED NOT NULL,
  contact_id BIGINT UNSIGNED NOT NULL,
  wa_message_id VARCHAR(255) NULL,
  direction ENUM('IN','OUT') NOT NULL,
  sender_type ENUM('USER','BOT','CRM','SYSTEM') NOT NULL,
  message_type VARCHAR(40) NOT NULL DEFAULT 'text',
  body TEXT NULL,
  payload_json LONGTEXT NULL,
  status VARCHAR(40) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(id), UNIQUE KEY uq_mg_wa_message(wa_message_id), KEY idx_mg_msg_conv(conversation_id,created_at), KEY idx_mg_msg_contact(contact_id,created_at),
  CONSTRAINT fk_mg_msg_conv FOREIGN KEY(conversation_id) REFERENCES mg_conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_mg_msg_contact FOREIGN KEY(contact_id) REFERENCES mg_contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mg_products (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(180) NOT NULL,
  short_name VARCHAR(80) NULL,
  emoji VARCHAR(16) NULL,
  description TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mg_orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  folio VARCHAR(40) NOT NULL,
  contact_id BIGINT UNSIGNED NOT NULL,
  full_name VARCHAR(180) NOT NULL,
  products_text TEXT NOT NULL,
  quantities_text TEXT NOT NULL,
  city VARCHAR(150) NOT NULL,
  delivery_type ENUM('pickup','shipping') NOT NULL,
  status ENUM('pending','confirmed','closed','cancelled') NOT NULL DEFAULT 'pending',
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(id), UNIQUE KEY uq_mg_order_folio(folio), KEY idx_mg_order_contact(contact_id), KEY idx_mg_order_status(status,created_at),
  CONSTRAINT fk_mg_order_contact FOREIGN KEY(contact_id) REFERENCES mg_contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mg_quotes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  folio VARCHAR(40) NOT NULL,
  contact_id BIGINT UNSIGNED NOT NULL,
  full_name VARCHAR(180) NULL,
  company VARCHAR(190) NULL,
  products_text TEXT NOT NULL,
  approximate_quantity VARCHAR(150) NOT NULL,
  needed_date DATE NULL,
  status ENUM('pending','review','quoted','closed','cancelled') NOT NULL DEFAULT 'pending',
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(id), UNIQUE KEY uq_mg_quote_folio(folio), KEY idx_mg_quote_contact(contact_id), KEY idx_mg_quote_status(status,created_at),
  CONSTRAINT fk_mg_quote_contact FOREIGN KEY(contact_id) REFERENCES mg_contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mg_sale_points (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(180) NOT NULL,
  city VARCHAR(150) NOT NULL,
  municipality VARCHAR(150) NULL,
  address VARCHAR(255) NULL,
  schedule VARCHAR(190) NULL,
  phone VARCHAR(50) NULL,
  map_url VARCHAR(500) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(id), KEY idx_mg_point_city(city), KEY idx_mg_point_active(active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mg_sale_point_queries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  contact_id BIGINT UNSIGNED NOT NULL,
  city_query VARCHAR(150) NOT NULL,
  matched_points INT NOT NULL DEFAULT 0,
  status ENUM('resolved_bot','pending','contacted','closed') NOT NULL DEFAULT 'resolved_bot',
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(id), KEY idx_mg_query_status(status,created_at),
  CONSTRAINT fk_mg_query_contact FOREIGN KEY(contact_id) REFERENCES mg_contacts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mg_contact_notes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  contact_id BIGINT UNSIGNED NOT NULL,
  admin_user_id INT UNSIGNED NULL,
  note TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(id), KEY idx_mg_note_contact(contact_id,created_at),
  CONSTRAINT fk_mg_note_contact FOREIGN KEY(contact_id) REFERENCES mg_contacts(id) ON DELETE CASCADE,
  CONSTRAINT fk_mg_note_admin FOREIGN KEY(admin_user_id) REFERENCES mg_admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mg_settings (
  setting_key VARCHAR(120) NOT NULL,
  setting_value TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO mg_admin_users(email,password_hash,full_name,role,active)
SELECT 'admin@meligil.com','$2y$12$TUSiHuq9eWnQZwD4d/..AOLMKkXfsWaNqJ885a2CzOVk0osTNbKJK','Administrador MeliGil','admin',1
WHERE NOT EXISTS (SELECT 1 FROM mg_admin_users WHERE email='admin@meligil.com');

INSERT INTO mg_products(name,short_name,emoji,description,active,sort_order)
SELECT 'Galleta Integral Individual','Galleta Integral','🍪','Galleta artesanal elaborada con ingredientes de calidad. Ideal para acompañar café, té o como snack durante el día.',1,10
WHERE NOT EXISTS(SELECT 1 FROM mg_products WHERE name='Galleta Integral Individual');
INSERT INTO mg_products(name,short_name,emoji,description,active,sort_order)
SELECT 'Minitamis','Minitamis','🧡','Presentación mini, práctica para llevar. Ideal para compartir, loncheras o antojos saludables.',1,20
WHERE NOT EXISTS(SELECT 1 FROM mg_products WHERE name='Minitamis');
INSERT INTO mg_products(name,short_name,emoji,description,active,sort_order)
SELECT 'Minimelis','Minimelis','💚','Presentación mini de nuestras galletas integrales. Perfectas para disfrutar en cualquier momento.',1,30
WHERE NOT EXISTS(SELECT 1 FROM mg_products WHERE name='Minimelis');
INSERT INTO mg_products(name,short_name,emoji,description,active,sort_order)
SELECT 'Granola Meligil','Granola Meligil','🌾','Elaborada con una mezcla de cereales e ingredientes seleccionados. Ideal para acompañar yogur, fruta o leche.',1,40
WHERE NOT EXISTS(SELECT 1 FROM mg_products WHERE name='Granola Meligil');

INSERT INTO mg_settings(setting_key,setting_value) VALUES
('marketing_template_name','marketing_meligil_texto'),
('marketing_template_language','es_MX'),
('firebase_crm_url','https://us-central1-meligilbot.cloudfunctions.net/crmSendMeligil')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

SET FOREIGN_KEY_CHECKS=1;
