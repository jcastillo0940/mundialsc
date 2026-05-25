USE super_carnes_mvp;

ALTER TABLE teams
  ADD COLUMN external_team_id BIGINT UNSIGNED NULL AFTER id,
  ADD COLUMN external_country_id BIGINT UNSIGNED NULL AFTER external_team_id,
  ADD COLUMN ranking_fifa INT UNSIGNED NULL AFTER code,
  ADD COLUMN provider_logo_url VARCHAR(255) NULL AFTER flag_emoji,
  ADD COLUMN provider_flag_path VARCHAR(120) NULL AFTER provider_logo_url,
  ADD UNIQUE KEY uk_teams_external_team_id (external_team_id);

ALTER TABLE tournament_matches
  ADD COLUMN external_fixture_id BIGINT UNSIGNED NULL AFTER id,
  ADD COLUMN external_match_id BIGINT UNSIGNED NULL AFTER external_fixture_id,
  ADD COLUMN external_group_id BIGINT UNSIGNED NULL AFTER external_match_id,
  ADD COLUMN provider VARCHAR(40) NOT NULL DEFAULT 'manual' AFTER status,
  ADD COLUMN provider_status VARCHAR(40) NULL AFTER provider,
  ADD COLUMN provider_competition_name VARCHAR(180) NULL AFTER provider_status,
  ADD COLUMN kickoff_timezone VARCHAR(40) NULL AFTER provider_competition_name,
  ADD COLUMN live_score_last_synced_at DATETIME NULL AFTER provider_status,
  ADD COLUMN commentary_last_synced_at DATETIME NULL AFTER live_score_last_synced_at,
  ADD COLUMN round_label VARCHAR(80) NULL AFTER group_label,
  ADD COLUMN stage_label VARCHAR(120) NULL AFTER round_label,
  ADD COLUMN venue_name VARCHAR(180) NULL AFTER stage_label,
  ADD COLUMN raw_provider_payload JSON NULL AFTER commentary_last_synced_at,
  ADD UNIQUE KEY uk_tournament_matches_external_fixture_id (external_fixture_id),
  ADD UNIQUE KEY uk_tournament_matches_external_match_id (external_match_id),
  ADD KEY idx_tournament_matches_external_group_id (external_group_id);

CREATE TABLE IF NOT EXISTS live_score_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  provider_name VARCHAR(40) NOT NULL DEFAULT 'live_score_api',
  is_enabled TINYINT(1) NOT NULL DEFAULT 0,
  competition_id VARCHAR(120) DEFAULT NULL,
  competition_ids VARCHAR(255) DEFAULT NULL,
  season VARCHAR(20) DEFAULT NULL,
  lang VARCHAR(10) DEFAULT 'en',
  sync_from_date DATE DEFAULT NULL,
  sync_to_date DATE DEFAULT NULL,
  auto_sync_commentary TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS live_score_sync_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sync_type ENUM('fixtures','live','commentary') NOT NULL,
  status ENUM('started','completed','failed') NOT NULL DEFAULT 'started',
  requested_by_user_id BIGINT UNSIGNED DEFAULT NULL,
  records_created INT NOT NULL DEFAULT 0,
  records_updated INT NOT NULL DEFAULT 0,
  records_skipped INT NOT NULL DEFAULT 0,
  context JSON DEFAULT NULL,
  error_message TEXT DEFAULT NULL,
  started_at DATETIME NOT NULL,
  finished_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_live_score_sync_runs_type_status (sync_type, status),
  CONSTRAINT fk_live_score_sync_runs_user FOREIGN KEY (requested_by_user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS live_score_commentary_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tournament_match_id BIGINT UNSIGNED NOT NULL,
  external_match_id BIGINT UNSIGNED NOT NULL,
  external_event_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(80) NOT NULL,
  minute VARCHAR(20) DEFAULT NULL,
  second_label VARCHAR(20) DEFAULT NULL,
  match_second INT DEFAULT NULL,
  comment_text TEXT DEFAULT NULL,
  text_label TEXT DEFAULT NULL,
  pos_x DECIMAL(6,2) DEFAULT NULL,
  pos_y DECIMAL(6,2) DEFAULT NULL,
  side CHAR(1) DEFAULT NULL,
  external_team_id BIGINT UNSIGNED DEFAULT NULL,
  team_name VARCHAR(150) DEFAULT NULL,
  external_player_id BIGINT UNSIGNED DEFAULT NULL,
  player_name VARCHAR(150) DEFAULT NULL,
  external_player_2_id BIGINT UNSIGNED DEFAULT NULL,
  player_2_name VARCHAR(150) DEFAULT NULL,
  provider_created_at DATETIME DEFAULT NULL,
  provider_updated_at DATETIME DEFAULT NULL,
  raw_payload JSON DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_live_score_commentary_external_event (external_event_id),
  KEY idx_live_score_commentary_match_second (tournament_match_id, match_second),
  CONSTRAINT fk_live_score_commentary_match FOREIGN KEY (tournament_match_id) REFERENCES tournament_matches(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO live_score_settings (
  id,
  provider_name,
  is_enabled,
  competition_id,
  competition_ids,
  season,
  lang,
  sync_from_date,
  sync_to_date,
  auto_sync_commentary
)
SELECT
  1,
  'live_score_api',
  0,
  NULL,
  NULL,
  NULL,
  'es',
  NULL,
  NULL,
  1
WHERE NOT EXISTS (SELECT 1 FROM live_score_settings WHERE id = 1);
