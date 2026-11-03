<?php

namespace App\Domain\Operations\Installation;

use PDO;
use Throwable;

final readonly class ResetIncompleteInstallation
{
    public const string CONFIRMATION = 'RESET WAYMARK INSTALLATION';

    public function __construct(
        private InstallationDatabaseOwnership $ownership,
        private InstallerDatabase $database = new InstallerDatabase,
    ) {}

    public function handle(
        DatabaseConfiguration $configuration,
        ?InstallationAttemptRecord $attempt,
        string $confirmation,
    ): IncompleteInstallationResetResult {
        if (! hash_equals(self::CONFIRMATION, $confirmation)) {
            return new IncompleteInstallationResetResult(false, 'Type the full destructive confirmation before resetting the incomplete installation.');
        }

        try {
            $pdo = $this->database->connect($configuration);
            $tables = $this->database->tables($pdo, $configuration->driver);
            $hasMarker = in_array(InstallationDatabaseOwnership::TABLE, $tables, true);

            if ($hasMarker) {
                if ($attempt === null || ! $this->ownership->matches($configuration, $attempt)) {
                    return $this->unsafe();
                }
            } else {
                $inspection = (new PdoDatabaseConnectionTester)->test($configuration);

                if ($inspection->state !== DatabaseInstallationState::Incomplete || ! $inspection->resetSafe) {
                    return $this->unsafe();
                }
            }

            if (! $this->tablesMatchKnownWaymarkSchema($pdo, $configuration->driver, $tables)) {
                return $this->unsafe();
            }

            $this->database->disableForeignKeyChecks($pdo, $configuration->driver);

            try {
                foreach ($tables as $table) {
                    $pdo->exec(sprintf('DROP TABLE %s', $this->database->quoteIdentifier($table, $configuration->driver)));
                }
            } finally {
                $this->database->enableForeignKeyChecks($pdo, $configuration->driver);
            }

            return new IncompleteInstallationResetResult(true, 'The incomplete Waymark installation was reset. Re-check the database details before retrying.');
        } catch (Throwable $exception) {
            report($exception);

            return new IncompleteInstallationResetResult(false, 'Waymark could not safely reset the incomplete installation. Use a fresh empty database or seek technical assistance.');
        }
    }

    /** @param list<string> $tables */
    private function tablesMatchKnownWaymarkSchema(PDO $pdo, string $driver, array $tables): bool
    {
        $expected = FreshInstallationSchema::load(resource_path('installation/fresh-schema.json'))->tables();

        foreach ($tables as $table) {
            if ($table === InstallationDatabaseOwnership::TABLE) {
                continue;
            }

            if (! isset($expected[$table])) {
                return false;
            }

            if (array_diff($this->database->columns($pdo, $driver, $table), $expected[$table]) !== []) {
                return false;
            }
        }

        return true;
    }

    private function unsafe(): IncompleteInstallationResetResult
    {
        return new IncompleteInstallationResetResult(
            false,
            'Waymark cannot prove that this database contains only an incomplete fresh installation. Use a fresh empty database or seek technical assistance.',
        );
    }
}
