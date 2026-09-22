<?php

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/response.php';

function isDatabaseConfigured(): bool
{
    return DB_NAME !== 'your_database_name'
        && DB_USER !== 'your_database_user'
        && trim(DB_NAME) !== ''
        && trim(DB_USER) !== '';
}

/**
 * @return array{connected: bool, message: string}
 */
function getDatabaseStatus(): array
{
    if (!isDatabaseConfigured()) {
        return [
            'connected' => false,
            'message' => 'Database is not configured. Update api/config.php, then import database/schema.sql and run php migrate.php (or import database/migrate.sql in phpMyAdmin).',
        ];
    }

    try {
        $pdo = getDb();
        $pdo->query('SELECT 1');

        $required = ['release_status', 'new_sprint', 'user', 'qa_data'];
        foreach ($required as $table) {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = ? AND table_name = ?'
            );
            $stmt->execute([DB_NAME, $table]);
            if ((int) $stmt->fetchColumn() === 0) {
                $legacyHint = hasLegacyTables($pdo)
                    ? ' Old tables (tickets, sprints, members) were detected. On a dev database only, you may run database/reset.sql then import schema.sql, migrate.sql, and optionally seed.sql.'
                    : '';

                return [
                    'connected' => false,
                    'message' => 'Database tables are missing. Import database/schema.sql, then run php migrate.php (or import database/migrate.sql in phpMyAdmin).' . $legacyHint,
                ];
            }
        }

        return ['connected' => true, 'message' => ''];
    } catch (PDOException $e) {
        logServerError($e);
        return [
            'connected' => false,
            'message' => 'Unable to connect to the database. Check DB_HOST, DB_NAME, DB_USER, and DB_PASS in api/config.php.',
        ];
    }
}

function hasLegacyTables(PDO $pdo): bool
{
    $legacy = ['tickets', 'sprints', 'members'];
    $required = ['qa_data', 'new_sprint', 'user'];
    $hasLegacy = false;
    $missingNew = false;

    foreach ($legacy as $table) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = ? AND table_name = ?'
        );
        $stmt->execute([DB_NAME, $table]);
        if ((int) $stmt->fetchColumn() > 0) {
            $hasLegacy = true;
            break;
        }
    }

    foreach ($required as $table) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = ? AND table_name = ?'
        );
        $stmt->execute([DB_NAME, $table]);
        if ((int) $stmt->fetchColumn() === 0) {
            $missingNew = true;
            break;
        }
    }

    return $hasLegacy && $missingNew;
}

function assertDatabaseConnection(): PDO
{
    $status = getDatabaseStatus();
    if (!$status['connected']) {
        jsonError($status['message'], 503, false);
    }

    return getDb();
}
