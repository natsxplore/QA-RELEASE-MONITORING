<?php

declare(strict_types=1);

function migrationTableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $stmt->execute([$table]);

    return (int) $stmt->fetchColumn() > 0;
}

function migrationColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Works on MySQL and MariaDB (no ADD COLUMN IF NOT EXISTS required).
 */
function migrationAddColumnIfNotExists(PDO $pdo, string $table, string $column, string $definition): void
{
    if (migrationColumnExists($pdo, $table, $column)) {
        return;
    }

    $table = str_replace('`', '``', $table);
    $column = str_replace('`', '``', $column);
    $pdo->exec(sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s', $table, $column, $definition));
}

function migrationEnsureMigrationsTable(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS migrations (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          migration VARCHAR(255) NOT NULL,
          batch INT UNSIGNED NOT NULL,
          applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY uk_migrations_migration (migration)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function migrationRecordSchemaVersion(PDO $pdo, string $version): void
{
    if (!migrationTableExists($pdo, 'schema_migrations')) {
        return;
    }

    $stmt = $pdo->query('SELECT version FROM schema_migrations LIMIT 1');
    $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

    if ($row === false) {
        $insert = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)');
        $insert->execute([$version]);

        return;
    }

    if (($row['version'] ?? '') === $version) {
        return;
    }

    $update = $pdo->prepare('UPDATE schema_migrations SET version = ?, applied_at = CURRENT_TIMESTAMP LIMIT 1');
    $update->execute([$version]);
}
