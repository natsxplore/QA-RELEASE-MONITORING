<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec(
        "INSERT INTO release_status (name) VALUES
        ('Released'),
        ('For release'),
        ('Not in release')
        ON DUPLICATE KEY UPDATE name = release_status.name"
    );
};
