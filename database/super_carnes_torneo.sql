USE super_carnes_mvp;

CREATE TABLE IF NOT EXISTS tournament_phases (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(80) NOT NULL,
  stage_order INT NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  exact_score_points INT NOT NULL DEFAULT 5,
  outcome_points INT NOT NULL DEFAULT 2,
  reset_phase_table TINYINT(1) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_tournament_phases_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teams (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  code VARCHAR(20) NOT NULL,
  ranking_fifa INT UNSIGNED DEFAULT NULL,
  group_label VARCHAR(10) DEFAULT NULL,
  flag_emoji VARCHAR(10) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_teams_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tournament_matches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  phase_id BIGINT UNSIGNED NOT NULL,
  match_number INT DEFAULT NULL,
  group_label VARCHAR(10) DEFAULT NULL,
  home_team_id BIGINT UNSIGNED NOT NULL,
  away_team_id BIGINT UNSIGNED NOT NULL,
  kickoff_at DATETIME NOT NULL,
  home_score INT DEFAULT NULL,
  away_score INT DEFAULT NULL,
  status ENUM('scheduled','locked','final') NOT NULL DEFAULT 'scheduled',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tournament_matches_phase_kickoff (phase_id, kickoff_at),
  CONSTRAINT fk_tournament_matches_phase FOREIGN KEY (phase_id) REFERENCES tournament_phases(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_tournament_matches_home FOREIGN KEY (home_team_id) REFERENCES teams(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_tournament_matches_away FOREIGN KEY (away_team_id) REFERENCES teams(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS match_predictions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  match_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  phase_id BIGINT UNSIGNED NOT NULL,
  predicted_home_score INT NOT NULL,
  predicted_away_score INT NOT NULL,
  points_awarded INT NOT NULL DEFAULT 0,
  result_type ENUM('pending','exact','outcome','miss') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_match_predictions_unique (match_id, user_id),
  KEY idx_match_predictions_user_phase (user_id, phase_id),
  CONSTRAINT fk_match_predictions_match FOREIGN KEY (match_id) REFERENCES tournament_matches(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_match_predictions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_match_predictions_phase FOREIGN KEY (phase_id) REFERENCES tournament_phases(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_goal_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  goal_value DECIMAL(8,2) NOT NULL DEFAULT 0.50,
  one_invoice_per_day TINYINT(1) NOT NULL DEFAULT 1,
  validation_mode ENUM('manual','external_db','api') NOT NULL DEFAULT 'manual',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS daily_invoice_goals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  phase_id BIGINT UNSIGNED NOT NULL,
  invoice_number VARCHAR(100) NOT NULL,
  invoice_date DATE NOT NULL,
  goal_points_awarded DECIMAL(8,2) NOT NULL DEFAULT 0.50,
  validation_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  validation_notes VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_daily_invoice_per_user_day (user_id, invoice_date),
  UNIQUE KEY uk_daily_invoice_number_day (invoice_number, invoice_date),
  KEY idx_daily_invoice_phase (phase_id),
  CONSTRAINT fk_daily_invoice_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_daily_invoice_phase FOREIGN KEY (phase_id) REFERENCES tournament_phases(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS phase_prizes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  phase_id BIGINT UNSIGNED NOT NULL,
  ranking_from INT NOT NULL,
  ranking_to INT NOT NULL,
  football_role VARCHAR(80) NOT NULL,
  prize_title VARCHAR(150) NOT NULL,
  prize_type VARCHAR(120) NOT NULL,
  stock INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_phase_prizes_phase (phase_id),
  CONSTRAINT fk_phase_prizes_phase FOREIGN KEY (phase_id) REFERENCES tournament_phases(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO invoice_goal_settings (id, is_enabled, goal_value, one_invoice_per_day, validation_mode)
SELECT 1, 1, 0.50, 1, 'manual'
WHERE NOT EXISTS (SELECT 1 FROM invoice_goal_settings WHERE id = 1);

INSERT INTO tournament_phases (name, slug, stage_order, starts_at, ends_at, exact_score_points, outcome_points, reset_phase_table, is_active)
SELECT 'Fase de Grupos', 'fase-grupos', 1, '2026-06-01 00:00:00', '2026-06-30 23:59:59', 5, 2, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM tournament_phases WHERE slug = 'fase-grupos');

INSERT INTO tournament_phases (name, slug, stage_order, starts_at, ends_at, exact_score_points, outcome_points, reset_phase_table, is_active)
SELECT 'Dieciseisavos de Final', 'dieciseisavos', 2, '2026-07-01 00:00:00', '2026-07-10 23:59:59', 10, 4, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM tournament_phases WHERE slug = 'dieciseisavos');

INSERT INTO tournament_phases (name, slug, stage_order, starts_at, ends_at, exact_score_points, outcome_points, reset_phase_table, is_active)
SELECT 'Octavos de Final', 'octavos', 3, '2026-07-11 00:00:00', '2026-07-18 23:59:59', 15, 6, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM tournament_phases WHERE slug = 'octavos');

INSERT INTO tournament_phases (name, slug, stage_order, starts_at, ends_at, exact_score_points, outcome_points, reset_phase_table, is_active)
SELECT 'Cuartos de Final', 'cuartos', 4, '2026-07-19 00:00:00', '2026-07-24 23:59:59', 20, 8, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM tournament_phases WHERE slug = 'cuartos');

INSERT INTO tournament_phases (name, slug, stage_order, starts_at, ends_at, exact_score_points, outcome_points, reset_phase_table, is_active)
SELECT 'Semifinal y Final', 'semifinal-final', 5, '2026-07-25 00:00:00', '2026-07-31 23:59:59', 30, 12, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM tournament_phases WHERE slug = 'semifinal-final');
