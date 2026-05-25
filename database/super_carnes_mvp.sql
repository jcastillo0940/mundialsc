-- Super Carnes MVP
-- Base de datos compatible con MySQL/InnoDB para WampServer
-- Charset recomendado: utf8mb4

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS super_carnes_mvp
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE super_carnes_mvp;

-- Limpieza opcional para reinstalacion
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS personal_access_tokens;
DROP TABLE IF EXISTS coupons;
DROP TABLE IF EXISTS game_plays;
DROP TABLE IF EXISTS instant_win_windows;
DROP TABLE IF EXISTS prize_inventory_movements;
DROP TABLE IF EXISTS prizes;
DROP TABLE IF EXISTS wallet_movements;
DROP TABLE IF EXISTS wallets;
DROP TABLE IF EXISTS registered_invoices;
DROP TABLE IF EXISTS campaign_settings;
DROP TABLE IF EXISTS campaigns;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS branches;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE branches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  code VARCHAR(30) NOT NULL,
  address VARCHAR(255) DEFAULT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_branches_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  branch_id BIGINT UNSIGNED DEFAULT NULL,
  role ENUM('client','cashier','admin') NOT NULL DEFAULT 'client',
  full_name VARCHAR(150) NOT NULL,
  cedula VARCHAR(40) NOT NULL,
  email VARCHAR(150) NOT NULL,
  google_id VARCHAR(191) DEFAULT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  email_verified_at TIMESTAMP NULL DEFAULT NULL,
  last_login_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_users_cedula (cedula),
  UNIQUE KEY uk_users_email (email),
  UNIQUE KEY uk_users_google_id (google_id),
  KEY idx_users_role (role),
  KEY idx_users_branch (branch_id),
  CONSTRAINT fk_users_branch
    FOREIGN KEY (branch_id) REFERENCES branches (id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE personal_access_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tokenable_type VARCHAR(120) NOT NULL,
  tokenable_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  token VARCHAR(64) NOT NULL,
  abilities TEXT DEFAULT NULL,
  last_used_at TIMESTAMP NULL DEFAULT NULL,
  expires_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_personal_access_tokens_token (token),
  KEY idx_personal_access_tokens_tokenable (tokenable_type, tokenable_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE campaigns (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  description TEXT DEFAULT NULL,
  status ENUM('draft','active','paused','closed') NOT NULL DEFAULT 'draft',
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  invoice_min_amount_for_shot DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  amount_per_point DECIMAL(12,2) NOT NULL DEFAULT 15.00,
  points_per_block INT NOT NULL DEFAULT 1,
  daily_max_points INT NOT NULL DEFAULT 50,
  daily_max_invoices INT NOT NULL DEFAULT 2,
  coupon_ttl_hours INT NOT NULL DEFAULT 72,
  games_enabled TINYINT(1) NOT NULL DEFAULT 0,
  major_prizes_enabled TINYINT(1) NOT NULL DEFAULT 0,
  invoice_scan_enabled TINYINT(1) NOT NULL DEFAULT 1,
  redemption_enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_campaigns_status_dates (status, starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE campaign_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  campaign_id BIGINT UNSIGNED NOT NULL,
  setting_key VARCHAR(100) NOT NULL,
  setting_value TEXT DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_campaign_settings_key (campaign_id, setting_key),
  CONSTRAINT fk_campaign_settings_campaign
    FOREIGN KEY (campaign_id) REFERENCES campaigns (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE registered_invoices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  branch_id BIGINT UNSIGNED DEFAULT NULL,
  cufe VARCHAR(255) NOT NULL,
  qr_raw_text LONGTEXT NOT NULL,
  invoice_number VARCHAR(80) DEFAULT NULL,
  fiscal_document_type VARCHAR(50) DEFAULT NULL,
  issued_at DATETIME DEFAULT NULL,
  purchase_amount DECIMAL(12,2) NOT NULL,
  points_awarded INT NOT NULL DEFAULT 0,
  shots_awarded INT NOT NULL DEFAULT 0,
  daily_points_capped TINYINT(1) NOT NULL DEFAULT 0,
  daily_invoice_limit_hit TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('accepted','duplicate','accepted_no_rewards') NOT NULL DEFAULT 'accepted',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_registered_invoices_cufe (cufe),
  KEY idx_registered_invoices_user_created (user_id, created_at),
  KEY idx_registered_invoices_campaign_created (campaign_id, created_at),
  KEY idx_registered_invoices_branch (branch_id),
  CONSTRAINT fk_registered_invoices_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT fk_registered_invoices_campaign
    FOREIGN KEY (campaign_id) REFERENCES campaigns (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT fk_registered_invoices_branch
    FOREIGN KEY (branch_id) REFERENCES branches (id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wallets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  goals_balance INT NOT NULL DEFAULT 0,
  shots_balance INT NOT NULL DEFAULT 0,
  lifetime_goals_earned INT NOT NULL DEFAULT 0,
  lifetime_shots_earned INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_wallets_user (user_id),
  CONSTRAINT fk_wallets_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wallet_movements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  wallet_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED DEFAULT NULL,
  type ENUM(
    'invoice_points_credit',
    'invoice_shots_credit',
    'redeem_points_debit',
    'game_shot_debit',
    'coupon_expire_restock',
    'manual_adjustment'
  ) NOT NULL,
  resource_type VARCHAR(50) DEFAULT NULL,
  resource_id BIGINT UNSIGNED DEFAULT NULL,
  goals_delta INT NOT NULL DEFAULT 0,
  shots_delta INT NOT NULL DEFAULT 0,
  notes VARCHAR(255) DEFAULT NULL,
  meta JSON DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_wallet_movements_wallet_created (wallet_id, created_at),
  KEY idx_wallet_movements_user_created (user_id, created_at),
  KEY idx_wallet_movements_campaign (campaign_id),
  CONSTRAINT fk_wallet_movements_wallet
    FOREIGN KEY (wallet_id) REFERENCES wallets (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_wallet_movements_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT fk_wallet_movements_campaign
    FOREIGN KEY (campaign_id) REFERENCES campaigns (id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE prizes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  campaign_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(150) NOT NULL,
  description TEXT DEFAULT NULL,
  category ENUM('major','consolation') NOT NULL,
  redemption_type ENUM('direct','instant_win') NOT NULL,
  points_cost INT DEFAULT NULL,
  shots_cost INT DEFAULT NULL,
  total_stock INT NOT NULL DEFAULT 0,
  reserved_stock INT NOT NULL DEFAULT 0,
  delivered_stock INT NOT NULL DEFAULT 0,
  image_url VARCHAR(255) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_prizes_campaign_slug (campaign_id, slug),
  KEY idx_prizes_campaign_type (campaign_id, redemption_type, is_active),
  CONSTRAINT fk_prizes_campaign
    FOREIGN KEY (campaign_id) REFERENCES campaigns (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE prize_inventory_movements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  prize_id BIGINT UNSIGNED NOT NULL,
  movement_type ENUM('reserve','release','deliver','manual_increase','manual_decrease') NOT NULL,
  quantity INT NOT NULL,
  related_coupon_id BIGINT UNSIGNED DEFAULT NULL,
  notes VARCHAR(255) DEFAULT NULL,
  created_by_user_id BIGINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_prize_inventory_prize_created (prize_id, created_at),
  KEY idx_prize_inventory_coupon (related_coupon_id),
  KEY idx_prize_inventory_user (created_by_user_id),
  CONSTRAINT fk_prize_inventory_prize
    FOREIGN KEY (prize_id) REFERENCES prizes (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_prize_inventory_user
    FOREIGN KEY (created_by_user_id) REFERENCES users (id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE instant_win_windows (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  campaign_id BIGINT UNSIGNED NOT NULL,
  prize_id BIGINT UNSIGNED NOT NULL,
  opens_at DATETIME NOT NULL,
  closes_at DATETIME NOT NULL,
  is_consumed TINYINT(1) NOT NULL DEFAULT 0,
  consumed_by_user_id BIGINT UNSIGNED DEFAULT NULL,
  consumed_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_windows_campaign_time (campaign_id, opens_at, closes_at),
  KEY idx_windows_prize_consumed (prize_id, is_consumed),
  KEY idx_windows_consumed_user (consumed_by_user_id),
  CONSTRAINT fk_windows_campaign
    FOREIGN KEY (campaign_id) REFERENCES campaigns (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_windows_prize
    FOREIGN KEY (prize_id) REFERENCES prizes (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_windows_consumed_user
    FOREIGN KEY (consumed_by_user_id) REFERENCES users (id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE game_plays (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  game_type ENUM('mete_gol','ruleta') NOT NULL,
  client_choice VARCHAR(100) DEFAULT NULL,
  result_type ENUM('major_prize','consolation_prize','no_win') NOT NULL,
  prize_id BIGINT UNSIGNED DEFAULT NULL,
  window_id BIGINT UNSIGNED DEFAULT NULL,
  shots_spent INT NOT NULL DEFAULT 1,
  played_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  meta JSON DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_game_plays_user_played (user_id, played_at),
  KEY idx_game_plays_campaign_played (campaign_id, played_at),
  KEY idx_game_plays_prize (prize_id),
  KEY idx_game_plays_window (window_id),
  CONSTRAINT fk_game_plays_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT fk_game_plays_campaign
    FOREIGN KEY (campaign_id) REFERENCES campaigns (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT fk_game_plays_prize
    FOREIGN KEY (prize_id) REFERENCES prizes (id)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT fk_game_plays_window
    FOREIGN KEY (window_id) REFERENCES instant_win_windows (id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE coupons (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NOT NULL,
  prize_id BIGINT UNSIGNED NOT NULL,
  source_type ENUM('direct_redemption','instant_win') NOT NULL,
  code CHAR(36) NOT NULL,
  qr_payload TEXT NOT NULL,
  status ENUM('generated','delivered','expired','cancelled') NOT NULL DEFAULT 'generated',
  expires_at DATETIME NOT NULL,
  delivered_at DATETIME DEFAULT NULL,
  delivered_by_user_id BIGINT UNSIGNED DEFAULT NULL,
  delivered_branch_id BIGINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_coupons_code (code),
  KEY idx_coupons_user_status (user_id, status),
  KEY idx_coupons_prize_status (prize_id, status),
  KEY idx_coupons_expires_at (expires_at),
  KEY idx_coupons_delivered_by (delivered_by_user_id),
  KEY idx_coupons_delivered_branch (delivered_branch_id),
  CONSTRAINT fk_coupons_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT fk_coupons_campaign
    FOREIGN KEY (campaign_id) REFERENCES campaigns (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT fk_coupons_prize
    FOREIGN KEY (prize_id) REFERENCES prizes (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT fk_coupons_delivered_by
    FOREIGN KEY (delivered_by_user_id) REFERENCES users (id)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT fk_coupons_delivered_branch
    FOREIGN KEY (delivered_branch_id) REFERENCES branches (id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE prize_inventory_movements
  ADD CONSTRAINT fk_prize_inventory_coupon
  FOREIGN KEY (related_coupon_id) REFERENCES coupons (id)
  ON UPDATE CASCADE
  ON DELETE SET NULL;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED DEFAULT NULL,
  actor_role VARCHAR(30) DEFAULT NULL,
  event_type VARCHAR(80) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id BIGINT UNSIGNED DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  payload JSON DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_logs_user_created (user_id, created_at),
  KEY idx_audit_logs_event_created (event_type, created_at),
  KEY idx_audit_logs_entity (entity_type, entity_id),
  CONSTRAINT fk_audit_logs_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vistas utiles para backend/reportes
CREATE OR REPLACE VIEW v_prize_stock AS
SELECT
  p.id,
  p.campaign_id,
  p.name,
  p.slug,
  p.category,
  p.redemption_type,
  p.total_stock,
  p.reserved_stock,
  p.delivered_stock,
  (p.total_stock - p.reserved_stock - p.delivered_stock) AS available_stock,
  p.is_active
FROM prizes p;

CREATE OR REPLACE VIEW v_wallet_summary AS
SELECT
  u.id AS user_id,
  u.full_name,
  u.cedula,
  u.email,
  u.role,
  COALESCE(w.goals_balance, 0) AS goals_balance,
  COALESCE(w.shots_balance, 0) AS shots_balance,
  COALESCE(w.lifetime_goals_earned, 0) AS lifetime_goals_earned,
  COALESCE(w.lifetime_shots_earned, 0) AS lifetime_shots_earned
FROM users u
LEFT JOIN wallets w ON w.user_id = u.id;

-- Semillas iniciales
INSERT INTO branches (name, code, address, phone, is_active) VALUES
('Sucursal Central', 'CENTRAL', 'Pendiente definir', NULL, 1),
('Sucursal Norte', 'NORTE', 'Pendiente definir', NULL, 1),
('Sucursal Sur', 'SUR', 'Pendiente definir', NULL, 1);

INSERT INTO users (branch_id, role, full_name, cedula, email, phone, password_hash, is_active)
VALUES
  (NULL, 'admin', 'Super Admin', 'ADMIN-0001', 'admin@supercarnes.local', NULL, '$2y$10$yQ/49Z.e.z6B5LjPgrt.pOMDpFEobLQtTY4/eKUaTgQHHq5hHhDRG', 1),
  (1, 'cashier', 'Caja Central', 'CASHIER-0001', 'caja.central@supercarnes.local', NULL, '$2y$10$yQ/49Z.e.z6B5LjPgrt.pOMDpFEobLQtTY4/eKUaTgQHHq5hHhDRG', 1);

INSERT INTO wallets (user_id, goals_balance, shots_balance, lifetime_goals_earned, lifetime_shots_earned)
SELECT id, 0, 0, 0, 0
FROM users
WHERE role = 'client';

INSERT INTO campaigns (
  name,
  description,
  status,
  starts_at,
  ends_at,
  invoice_min_amount_for_shot,
  amount_per_point,
  points_per_block,
  daily_max_points,
  daily_max_invoices,
  coupon_ttl_hours,
  games_enabled,
  major_prizes_enabled,
  invoice_scan_enabled,
  redemption_enabled
) VALUES (
  'Campana MVP Inicial',
  'Campana base para desarrollo y pruebas del MVP.',
  'active',
  '2026-05-22 00:00:00',
  '2026-12-31 23:59:59',
  15.00,
  15.00,
  1,
  50,
  2,
  72,
  0,
  0,
  1,
  1
);

INSERT INTO prizes (
  campaign_id,
  name,
  slug,
  description,
  category,
  redemption_type,
  points_cost,
  shots_cost,
  total_stock,
  reserved_stock,
  delivered_stock,
  is_active
) VALUES
  (1, 'Televisor 43 Pulgadas', 'televisor-43', 'Premio mayor controlado por stock.', 'major', 'direct', 500, NULL, 10, 0, 0, 1),
  (1, 'Ketchup', 'ketchup', 'Premio de consolacion.', 'consolation', 'instant_win', NULL, 1, 100, 0, 0, 1),
  (1, 'Yogurt', 'yogurt', 'Premio de consolacion.', 'consolation', 'instant_win', NULL, 1, 100, 0, 0, 1),
  (1, 'Panito', 'panito', 'Premio de consolacion.', 'consolation', 'instant_win', NULL, 1, 100, 0, 0, 1);

-- Trigger: crear wallet automaticamente cuando se cree un cliente
DELIMITER $$

CREATE TRIGGER trg_users_after_insert_wallet
AFTER INSERT ON users
FOR EACH ROW
BEGIN
  IF NEW.role = 'client' THEN
    INSERT INTO wallets (
      user_id,
      goals_balance,
      shots_balance,
      lifetime_goals_earned,
      lifetime_shots_earned
    ) VALUES (
      NEW.id,
      0,
      0,
      0,
      0
    );
  END IF;
END$$

CREATE TRIGGER trg_coupons_before_insert_payload
BEFORE INSERT ON coupons
FOR EACH ROW
BEGIN
  IF NEW.code IS NULL OR NEW.code = '' THEN
    SET NEW.code = UUID();
  END IF;
END$$

DELIMITER ;

-- Evento opcional para expiracion de cupones.
-- Requiere activar event_scheduler en MySQL/WampServer:
-- SET GLOBAL event_scheduler = ON;
--
-- DELIMITER $$
-- CREATE EVENT ev_expire_coupons
-- ON SCHEDULE EVERY 1 MINUTE
-- DO
-- BEGIN
--   UPDATE coupons
--   SET status = 'expired',
--       updated_at = CURRENT_TIMESTAMP
--   WHERE status = 'generated'
--     AND expires_at < NOW();
-- END$$
-- DELIMITER ;
