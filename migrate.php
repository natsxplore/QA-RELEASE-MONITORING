<?php

/**
 * Laravel-style schema migrations (CLI only). Safe on databases with existing data.
 *
 *   php migrate.php              Run pending migrations
 *   php migrate.php status       Show applied / pending
 *   php migrate.php --pretend    List what would run (no SQL)
 *   php migrate.php sync-legacy  Record migrations as done (after manual migrate.sql)
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Run this script from the command line: php migrate.php\n";
    exit(1);
}

require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/migration_runner.php';

function migratePrintUsage(): void
{
    echo "QA Release Monitoring — database migrations\n\n";
    echo "Usage:\n";
    echo "  php migrate.php              Run pending migrations (does not delete data)\n";
    echo "  php migrate.php status       List migration status\n";
    echo "  php migrate.php --pretend    Dry run\n";
    echo "  php migrate.php sync-legacy  Mark all migrations applied (use if you already imported migrate.sql)\n\n";
    echo "Requires api/config.php and database/schema.sql imported at least once.\n";
}

function migrateFail(string $message, int $code = 1): void
{
    fwrite(STDERR, "Error: {$message}\n");
    exit($code);
}

$command = 'migrate';
$pretend = false;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        migratePrintUsage();
        exit(0);
    }
    if ($arg === '--pretend' || $arg === '-n') {
        $pretend = true;
        continue;
    }
    if ($arg === 'status' || $arg === 'sync-legacy' || $arg === 'migrate') {
        $command = $arg;
        continue;
    }
    migrateFail('Unknown argument: ' . $arg . '. Use --help.');
}

if (!isDatabaseConfigured()) {
    migrateFail('Configure api/config.php (copy from api/config.example.php) before migrating.');
}

try {
    $pdo = getDb();
    $pdo->exec('SET NAMES utf8mb4');
} catch (Throwable $e) {
    migrateFail('Database connection failed: ' . $e->getMessage());
}

$requiredTables = ['qa_data', 'new_sprint', 'user', 'release_status'];
foreach ($requiredTables as $table) {
    if (!migrationTableExists($pdo, $table)) {
        migrateFail(
            "Table \"{$table}\" is missing. Import database/schema.sql first, then run php migrate.php again."
        );
    }
}

try {
    if ($command === 'status') {
        $status = migrationStatus($pdo);
        $appliedSet = array_fill_keys($status['applied'], true);
        echo "Migration status\n";
        echo str_repeat('-', 72) . "\n";
        foreach ($status['all'] as $name) {
            $label = isset($appliedSet[$name]) ? 'Ran' : 'Pending';
            echo sprintf("  [%s] %s\n", $label, $name);
        }
        echo str_repeat('-', 72) . "\n";
        echo count($status['pending']) . " pending, " . count($status['applied']) . " applied.\n";
        exit(0);
    }

    if ($command === 'sync-legacy') {
        $count = migrationSyncLegacy($pdo);
        if ($count === 0) {
            echo "Nothing to sync — all migrations are already recorded.\n";
        } else {
            echo "Recorded {$count} migration(s) without running SQL (legacy sync).\n";
        }
        exit(0);
    }

    $status = migrationStatus($pdo);
    if ($status['pending'] === []) {
        echo "Nothing to migrate.\n";
        exit(0);
    }

    if ($pretend) {
        echo "Pending migrations (dry run):\n";
        foreach ($status['pending'] as $name) {
            echo "  - {$name}\n";
        }
        exit(0);
    }

    $count = migrationRunPending($pdo, false);
    echo "Migrated successfully ({$count} migration(s)).\n";
    exit(0);
} catch (Throwable $e) {
    migrateFail($e->getMessage());
}
