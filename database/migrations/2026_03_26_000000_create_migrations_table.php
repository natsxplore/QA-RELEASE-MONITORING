<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/migration_helpers.php';

return static function (PDO $pdo): void {
    migrationEnsureMigrationsTable($pdo);
};
