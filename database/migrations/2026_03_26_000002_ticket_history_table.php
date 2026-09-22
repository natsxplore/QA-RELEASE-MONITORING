<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS ticket_history (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
