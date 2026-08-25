<?php

namespace App\Domain\Operations\Installation;

use App\Domain\Operations\Installation\Contracts\DatabaseConnectionTester;
use PDO;
use Throwable;

final class PdoDatabaseConnectionTester implements DatabaseConnectionTester
{
    public function test(DatabaseConfiguration $configuration): DatabaseConnectionResult
    {
        $pdo = null;
        $probeTable = 'waymark_preflight_'.bin2hex(random_bytes(8));
        $probeCreated = false;
        $operation = 'connect';

        try {
            $pdo = new PDO(
                $this->dsn($configuration),
                $configuration->username,
                $configuration->password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5],
            );
            $pdo->query('SELECT 1')?->fetchColumn();

            $tables = $this->tables($pdo, $configuration->driver);

            if ($tables !== []) {
                return $this->inspectExistingDatabase($pdo, $configuration, $tables);
            }

            $operation = 'create';
            $pdo->exec(sprintf('CREATE TABLE %s (id INTEGER PRIMARY KEY, value VARCHAR(32) NOT NULL)', $this->quoteIdentifier($probeTable, $configuration->driver)));
            $probeCreated = true;

            $operation = 'insert';
            $pdo->exec(sprintf("INSERT INTO %s (id, value) VALUES (1, 'created')", $this->quoteIdentifier($probeTable, $configuration->driver)));

            $operation = 'select';
            $selected = $pdo->query(sprintf('SELECT value FROM %s WHERE id = 1', $this->quoteIdentifier($probeTable, $configuration->driver)))?->fetchColumn();
            if ($selected !== 'created') {
                throw new \RuntimeException('Database preflight SELECT returned an unexpected value.');
            }

            $operation = 'update';
            $pdo->exec(sprintf("UPDATE %s SET value = 'updated' WHERE id = 1", $this->quoteIdentifier($probeTable, $configuration->driver)));

            $operation = 'alter';
            $pdo->exec(sprintf('ALTER TABLE %s ADD COLUMN changed_at VARCHAR(32) NULL', $this->quoteIdentifier($probeTable, $configuration->driver)));

            $operation = 'drop';
            $pdo->exec(sprintf('DROP TABLE %s', $this->quoteIdentifier($probeTable, $configuration->driver)));
            $probeCreated = false;

            return new DatabaseConnectionResult(
                true,
                'Database connection and schema permissions verified.',
                DatabaseInstallationState::Empty,
                fingerprint: $this->fingerprint($configuration),
            );
        } catch (Throwable) {
            return new DatabaseConnectionResult(
                false,
                $this->failureMessage($operation),
                DatabaseInstallationState::Ambiguous,
                fingerprint: $this->fingerprint($configuration),
            );
        } finally {
            if ($probeCreated && $pdo instanceof PDO) {
                try {
                    $pdo->exec(sprintf('DROP TABLE %s', $this->quoteIdentifier($probeTable, $configuration->driver)));
                } catch (Throwable) {
                    // The actionable public failure above already explains the
                    // missing permission. Detailed errors remain server-side.
                }
            }
        }
    }

    /** @return list<string> */
    private function tables(PDO $pdo, string $driver): array
    {
        if ($driver === 'sqlite') {
            $statement = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        } else {
            $statement = $pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME');
        }

        return $statement === false ? [] : array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }

    private function quoteIdentifier(string $identifier, string $driver): string
    {
        return $driver === 'sqlite'
            ? '"'.str_replace('"', '""', $identifier).'"'
            : '`'.str_replace('`', '``', $identifier).'`';
    }

    /** @param list<string> $tables */
    private function inspectExistingDatabase(PDO $pdo, DatabaseConfiguration $configuration, array $tables): DatabaseConnectionResult
    {
        $ambiguous = new DatabaseConnectionResult(
            false,
            'This database already contains data that Waymark cannot safely use for a fresh installation. Use a new empty database or seek technical assistance.',
            DatabaseInstallationState::Ambiguous,
            fingerprint: $this->fingerprint($configuration),
        );

        if (! in_array('migrations', $tables, true)) {
            return $ambiguous;
        }

        try {
            $manifest = FreshInstallationSchema::load(resource_path('installation/fresh-schema.json'));
            $expectedTables = $manifest->tables();

            foreach ($tables as $table) {
                if (! array_key_exists($table, $expectedTables)) {
                    return $ambiguous;
                }

                $unexpectedColumns = array_diff(
                    $this->columns($pdo, $configuration->driver, $table),
                    $expectedTables[$table],
                );

                if ($unexpectedColumns !== []) {
                    return $ambiguous;
                }
            }

            $migrationRows = $pdo
                ->query(sprintf('SELECT migration FROM %s ORDER BY migration', $this->quoteIdentifier('migrations', $configuration->driver)))
                ?->fetchAll(PDO::FETCH_COLUMN);
            $recordedMigrations = array_values(array_unique(array_map('strval', is_array($migrationRows) ? $migrationRows : [])));
            sort($recordedMigrations);

            if ($recordedMigrations === [] || array_diff($recordedMigrations, $manifest->migrationNames()) !== []) {
                return $ambiguous;
            }

            $complete = $recordedMigrations === $manifest->migrationNames()
                && $tables === array_keys($expectedTables)
                && $this->schemaMatchesExactly($pdo, $configuration->driver, $expectedTables);

            if ($complete) {
                return new DatabaseConnectionResult(
                    false,
                    'This database already contains a completed Waymark schema. Setup will not overwrite it.',
                    DatabaseInstallationState::Complete,
                    fingerprint: $this->fingerprint($configuration),
                );
            }

            return new DatabaseConnectionResult(
                false,
                'An incomplete Waymark installation was detected. Review the recovery option before retrying setup.',
                DatabaseInstallationState::Incomplete,
                resetSafe: true,
                fingerprint: $this->fingerprint($configuration),
            );
        } catch (Throwable) {
            return $ambiguous;
        }
    }

    /** @param array<string, list<string>> $expectedTables */
    private function schemaMatchesExactly(PDO $pdo, string $driver, array $expectedTables): bool
    {
        foreach ($expectedTables as $table => $expectedColumns) {
            if ($this->columns($pdo, $driver, $table) !== $expectedColumns) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private function columns(PDO $pdo, string $driver, string $table): array
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

    private function fingerprint(DatabaseConfiguration $configuration): string
    {
        return hash('sha256', implode('|', [
            $configuration->driver,
            strtolower($configuration->host),
            (string) ($configuration->port ?? ''),
            $configuration->database,
            $configuration->username,
        ]));
    }

    private function failureMessage(string $operation): string
    {
        return match ($operation) {
            'create' => 'The database account can connect but cannot create tables. Grant the required database permissions or use another database account.',
            'insert', 'select', 'update' => 'The database account can connect but cannot read and change table data. Grant the required database permissions or use another database account.',
            'alter' => 'The database account can connect but cannot alter tables. Grant the required database permissions or use another database account.',
            'drop' => 'The database account can connect but cannot drop temporary tables. Grant the required database permissions or use another database account.',
            default => 'Waymark could not connect using those database details.',
        };
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
