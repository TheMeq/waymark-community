<?php

namespace App\Domain\Operations\Installation;

use PDO;

final class InstallerDatabase
{
    public function connect(DatabaseConfiguration $configuration): PDO
    {
        return new PDO(
            $this->dsn($configuration),
            $configuration->username,
            $configuration->password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5],
        );
    }

    /** @return list<string> */
    public function tables(PDO $pdo, string $driver): array
    {
        $statement = $driver === 'sqlite'
            ? $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
            : $pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME');

        return $statement === false ? [] : array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }

    /** @return list<string> */
    public function columns(PDO $pdo, string $driver, string $table): array
    {
        if ($driver === 'sqlite') {
            $statement = $pdo->query(sprintf('PRAGMA table_info(%s)', $this->quoteIdentifier($table, $driver)));
            $rows = $statement === false ? [] : $statement->fetchAll(PDO::FETCH_ASSOC);
            $columns = array_map(static fn (array $row): string => (string) $row['name'], $rows);
        } else {
            $statement = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION');
            $statement->execute([$table]);
            $columns = array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
        }

        sort($columns);

        return array_values($columns);
    }

    public function quoteIdentifier(string $identifier, string $driver): string
    {
        return $driver === 'sqlite'
            ? '"'.str_replace('"', '""', $identifier).'"'
            : '`'.str_replace('`', '``', $identifier).'`';
    }

    public function disableForeignKeyChecks(PDO $pdo, string $driver): void
    {
        $pdo->exec($driver === 'sqlite' ? 'PRAGMA foreign_keys = OFF' : 'SET FOREIGN_KEY_CHECKS = 0');
    }

    public function enableForeignKeyChecks(PDO $pdo, string $driver): void
    {
        $pdo->exec($driver === 'sqlite' ? 'PRAGMA foreign_keys = ON' : 'SET FOREIGN_KEY_CHECKS = 1');
    }

    private function dsn(DatabaseConfiguration $configuration): string
    {
        if ($configuration->driver === 'sqlite') {
            return 'sqlite:'.$configuration->database;
        }

        if (! in_array($configuration->driver, ['mysql', 'mariadb'], true)) {
            throw new \InvalidArgumentException('Unsupported database driver.');
        }

        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $configuration->host,
            $configuration->port ?? 3306,
            $configuration->database,
        );
    }
}
