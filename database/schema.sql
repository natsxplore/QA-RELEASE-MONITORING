-- QA Release Monitoring — database STRUCTURE (safe to re-run)
-- Tables: release_status, new_sprint, user, qa_data
-- Does NOT drop tables and does NOT delete data.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS release_status (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL,
  UNIQUE KEY uk_release_status_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS new_sprint (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  UNIQUE KEY uk_new_sprint_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user` (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  role ENUM('Developer','QA') NOT NULL,
  UNIQUE KEY uk_user_name_role (name, role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS qa_data (
  id INT AUTO_INCREMENT PRIMARY KEY,
  new_sprint_id INT NOT NULL,
  change_id VARCHAR(32) NOT NULL,
  title VARCHAR(500) NOT NULL,
  owner_user_id INT NOT NULL,
  qa_user_id INT NULL,
  change_stage VARCHAR(80) NOT NULL DEFAULT 'Development',
  change_status VARCHAR(80) NOT NULL DEFAULT 'Active',
  change_type ENUM('Emergency','Normal','Standard') NOT NULL,
  created_time DATETIME NOT NULL,
  release_status_id INT NOT NULL,
  remarks TEXT NULL,
  UNIQUE KEY uk_qa_data_change_id (change_id),
  KEY idx_qa_data_new_sprint_id (new_sprint_id),
  KEY idx_qa_data_release_status_id (release_status_id),
  KEY idx_qa_data_created_time (created_time),
  CONSTRAINT fk_qa_data_sprint FOREIGN KEY (new_sprint_id) REFERENCES new_sprint(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_qa_data_owner FOREIGN KEY (owner_user_id) REFERENCES `user`(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_qa_data_qa FOREIGN KEY (qa_user_id) REFERENCES `user`(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_qa_data_release_status FOREIGN KEY (release_status_id) REFERENCES release_status(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT NULL,
  change_id VARCHAR(32) NOT NULL,
  action ENUM('created','updated','deleted','imported','transferred') NOT NULL,
  details JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ticket_history_ticket_id (ticket_id),
  KEY idx_ticket_history_change_id (change_id),
  KEY idx_ticket_history_created_at (created_at),
  CONSTRAINT fk_ticket_history_ticket FOREIGN KEY (ticket_id) REFERENCES qa_data(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(32) NOT NULL PRIMARY KEY,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
