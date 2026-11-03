<?php

namespace App\Domain\Operations\Installation;

use RuntimeException;

final readonly class InstallationDatabaseOwnership
{
    public const string TABLE = 'waymark_installation_attempts';

    public function __construct(private InstallerDatabase $database = new InstallerDatabase) {}

    public function claim(DatabaseConfiguration $configuration, InstallationAttemptRecord $attempt): void
    {
        $pdo = $this->database->connect($configuration);

        if ($this->database->tables($pdo, $configuration->driver) !== []) {
            throw new RuntimeException('Waymark cannot claim a database that is not empty.');
        }

        $table = $this->database->quoteIdentifier(self::TABLE, $configuration->driver);
        $pdo->exec(sprintf(
            'CREATE TABLE %s (id VARCHAR(32) PRIMARY KEY, token_hash VARCHAR(64) NOT NULL, migration_set_hash VARCHAR(64) NOT NULL, created_at VARCHAR(40) NOT NULL)',
            $table,
        ));
        $statement = $pdo->prepare(sprintf(
            'INSERT INTO %s (id, token_hash, migration_set_hash, created_at) VALUES (?, ?, ?, ?)',
            $table,
        ));
        $statement->execute([
            $attempt->id,
            hash('sha256', $attempt->ownershipToken),
            $attempt->migrationSetHash,
            $attempt->createdAt,
        ]);
    }

    public function matches(DatabaseConfiguration $configuration, InstallationAttemptRecord $attempt): bool
    {
        $pdo = $this->database->connect($configuration);

        if (! in_array(self::TABLE, $this->database->tables($pdo, $configuration->driver), true)) {
            return false;
        }

        $statement = $pdo->prepare(sprintf(
            'SELECT token_hash, migration_set_hash FROM %s WHERE id = ?',
            $this->database->quoteIdentifier(self::TABLE, $configuration->driver),
        ));
        $statement->execute([$attempt->id]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return is_array($row)
            && hash_equals((string) $row['token_hash'], hash('sha256', $attempt->ownershipToken))
            && hash_equals((string) $row['migration_set_hash'], $attempt->migrationSetHash);
    }

    public function release(DatabaseConfiguration $configuration, InstallationAttemptRecord $attempt): void
    {
        if (! $this->matches($configuration, $attempt)) {
            throw new RuntimeException('Waymark could not verify the fresh-install database ownership marker.');
        }

        $pdo = $this->database->connect($configuration);
        $pdo->exec(sprintf(
            'DROP TABLE %s',
            $this->database->quoteIdentifier(self::TABLE, $configuration->driver),
        ));
    }
}
