-- QA Release Monitoring — incremental structure updates (safe to re-run)
-- Run AFTER database/schema.sql on every deploy when the app version changes.
-- Uses MariaDB/MySQL compatible patterns; existing rows are preserved.
--
-- Prefer CLI when available (same steps, tracked per file):
--   php migrate.php
-- If you already imported this file manually: php migrate.php sync-legacy
--
-- When adding schema changes, add a matching file under database/migrations/
-- AND keep this file in sync for phpMyAdmin-only deploys.
-- Each block should be idempotent (safe if run twice).

SET NAMES utf8mb4;

-- Required lookup values (app works without database/seed.sql)
INSERT INTO release_status (name) VALUES
('Released'),
('For release'),
('Not in release')
ON DUPLICATE KEY UPDATE name = release_status.name;

-- Ticket action history (create, update, delete, import)
CREATE TABLE IF NOT EXISTS ticket_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT NULL,
  change_id VARCHAR(32) NOT NULL,
  action ENUM('created','updated','deleted','imported') NOT NULL,
  details JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ticket_history_ticket_id (ticket_id),
  KEY idx_ticket_history_change_id (change_id),
  KEY idx_ticket_history_created_at (created_at),
  CONSTRAINT fk_ticket_history_ticket FOREIGN KEY (ticket_id) REFERENCES qa_data(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE qa_data ADD COLUMN IF NOT EXISTS remarks TEXT NULL;

ALTER TABLE ticket_history
  MODIFY action ENUM('created','updated','deleted','imported','transferred') NOT NULL;

INSERT INTO schema_migrations (version) VALUES ('2026.03.26.5')
ON DUPLICATE KEY UPDATE version = schema_migrations.version;
