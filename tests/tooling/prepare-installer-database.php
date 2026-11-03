<?php

declare(strict_types=1);

[$script, $driver, $host, $port, $database, $username, $password, $scenario] = array_pad($argv, 8, null);

if (! in_array($driver, ['mysql', 'mariadb'], true)) {
    throw new RuntimeException('Fixture driver must be mysql or mariadb.');
}

if (! in_array($scenario, ['exact-partial', 'generic-partial', 'ambiguous'], true)) {
    throw new RuntimeException('Unsupported installer database fixture.');
}

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, (int) $port, $database),
    (string) $username,
    (string) $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
$tables = $pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchAll(PDO::FETCH_COLUMN);

if ($tables !== []) {
    throw new RuntimeException('Installer fixture database must be empty.');
}

if ($scenario === 'ambiguous') {
    $pdo->exec('CREATE TABLE unrelated_records (id BIGINT UNSIGNED NOT NULL PRIMARY KEY)');
    exit(0);
}

$pdo->exec('CREATE TABLE migrations (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, migration VARCHAR(255) NOT NULL, batch INT NOT NULL)');

if ($scenario === 'exact-partial') {
    $pdo->exec('CREATE TABLE site_media (id BIGINT UNSIGNED NOT NULL PRIMARY KEY, regeneration_cleanup_status VARCHAR(255) NULL)');
    $migration = '2026_08_22_101000_harden_site_media_focal_points';
} else {
    $pdo->exec('CREATE TABLE users (id BIGINT UNSIGNED NOT NULL PRIMARY KEY)');
    $migration = '0001_01_01_000000_create_users_table';
}

$statement = $pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (?, 1)');
$statement->execute([$migration]);
