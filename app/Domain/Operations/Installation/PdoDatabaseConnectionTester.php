<?php

namespace App\Domain\Operations\Installation;

use App\Domain\Operations\Installation\Contracts\DatabaseConnectionTester;
use PDO;
use Throwable;

final class PdoDatabaseConnectionTester implements DatabaseConnectionTester
{
    public function test(DatabaseConfiguration $configuration): DatabaseConnectionResult
    {
        try {
            $pdo = new PDO(
                $this->dsn($configuration),
                $configuration->username,
                $configuration->password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5],
            );
            $pdo->query('SELECT 1')?->fetchColumn();

            return new DatabaseConnectionResult(true, 'Connection successful.');
        } catch (Throwable) {
            return new DatabaseConnectionResult(false, 'Waymark could not connect using those database details.');
        }
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
