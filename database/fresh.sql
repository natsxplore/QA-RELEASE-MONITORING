-- QA Release Monitoring — empty database, current structure kept
-- Removes ALL rows from app tables; does NOT drop tables or columns.
-- Dev/local only.
--
-- After import:
--   • App works (release_status lookups restored below).
--   • Optional demo data: import database/seed.sql
--
-- To rebuild tables from scratch (old schema / wrong structure):
--   database/reset.sql → database/schema.sql → database/migrate.sql → optional seed.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE ticket_history;
TRUNCATE TABLE qa_data;
TRUNCATE TABLE `user`;
TRUNCATE TABLE new_sprint;
TRUNCATE TABLE release_status;
TRUNCATE TABLE schema_migrations;

SET FOREIGN_KEY_CHECKS = 1;

-- Required lookup values (same as database/migrate.sql)
INSERT INTO release_status (name) VALUES
('Released'),
('For release'),
('Not in release')
ON DUPLICATE KEY UPDATE name = release_status.name;

INSERT INTO schema_migrations (version) VALUES ('2026.03.26.5')
ON DUPLICATE KEY UPDATE version = schema_migrations.version;
