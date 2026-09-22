<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/migration_helpers.php';

return static function (PDO $pdo): void {
    if (!migrationTableExists($pdo, 'ticket_history')) {
        return;
    }

    $pdo->exec(
        "ALTER TABLE ticket_history
          MODIFY action ENUM('created','updated','deleted','imported','transferred') NOT NULL"
    );
};
