<?php

declare(strict_types=1);

require_once __DIR__ . '/migration_helpers.php';

/**
 * @return list<string> Basenames sorted lexicographically (Laravel-style ordering).
 */
function migrationDiscoverFiles(): array
{
    $dir = dirname(__DIR__) . '/database/migrations';
    if (!is_dir($dir)) {
        return [];
    }

    $files = glob($dir . '/*.php') ?: [];
    sort($files, SORT_STRING);

    return array_map(static fn (string $path): string => basename($path), $files);
}

/**
 * @return list<string>
 */
function migrationGetApplied(PDO $pdo): array
{
    if (!migrationTableExists($pdo, 'migrations')) {
        return [];
    }

    $stmt = $pdo->query('SELECT migration FROM migrations ORDER BY id');

    if ($stmt === false) {
        return [];
    }

    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

    return is_array($rows) ? array_values(array_map('strval', $rows)) : [];
}

function migrationNextBatch(PDO $pdo): int
{
    if (!migrationTableExists($pdo, 'migrations')) {
        return 1;
    }

    $stmt = $pdo->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations');
    if ($stmt === false) {
        return 1;
    }

    return max(1, (int) $stmt->fetchColumn());
}

/**
 * @return object{up: callable(PDO): void}|callable(PDO): void
 */
function migrationLoad(string $basename)
{
    $path = dirname(__DIR__) . '/database/migrations/' . $basename;
    if (!is_file($path)) {
        throw new RuntimeException('Migration file not found: ' . $basename);
    }

    $migration = require $path;
    if (!is_object($migration) && !is_callable($migration)) {
        throw new RuntimeException('Migration must return a callable or an object with up(): ' . $basename);
    }

    return $migration;
}

function migrationInvokeUp($migration, PDO $pdo): void
{
    if (is_callable($migration)) {
        $migration($pdo);

        return;
    }

    if (is_object($migration) && method_exists($migration, 'up')) {
        $migration->up($pdo);

        return;
    }

    throw new RuntimeException('Invalid migration export.');
}

/**
 * @return array{applied: list<string>, pending: list<string>, all: list<string>}
 */
function migrationStatus(PDO $pdo): array
{
    $all = migrationDiscoverFiles();
    $applied = migrationGetApplied($pdo);
    $appliedSet = array_fill_keys($applied, true);
    $pending = [];

    foreach ($all as $name) {
        if (!isset($appliedSet[$name])) {
            $pending[] = $name;
        }
    }

    return ['applied' => $applied, 'pending' => $pending, 'all' => $all];
}

/**
 * Run pending migrations. Returns count applied.
 */
function migrationRunPending(PDO $pdo, bool $pretend = false): int
{
    $status = migrationStatus($pdo);
    if ($status['pending'] === []) {
        return 0;
    }

    $batch = migrationNextBatch($pdo);
    $count = 0;

    foreach ($status['pending'] as $basename) {
        if ($pretend) {
            $count++;

            continue;
        }

        $migration = migrationLoad($basename);

        try {
            // MySQL/MariaDB commit DDL implicitly; wrapping in a transaction breaks commit/rollback.
            migrationInvokeUp($migration, $pdo);

            if (!migrationTableExists($pdo, 'migrations')) {
                throw new RuntimeException('migrations table missing after ' . $basename);
            }

            $stmt = $pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (?, ?)');
            $stmt->execute([$basename, $batch]);
        } catch (Throwable $e) {
            throw new RuntimeException('Migration failed: ' . $basename . ' — ' . $e->getMessage(), 0, $e);
        }

        $count++;
    }

    return $count;
}

/**
 * Mark all discovered migrations as applied without running SQL (for DBs already updated via migrate.sql).
 */
function migrationSyncLegacy(PDO $pdo): int
{
    migrationEnsureMigrationsTable($pdo);
    $status = migrationStatus($pdo);
    if ($status['pending'] === []) {
        return 0;
    }

    $batch = migrationNextBatch($pdo);
    $stmt = $pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (?, ?)');
    $count = 0;

    foreach ($status['pending'] as $basename) {
        $stmt->execute([$basename, $batch]);
        $count++;
    }

    return $count;
}
