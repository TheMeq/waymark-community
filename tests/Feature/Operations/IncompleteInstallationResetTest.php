<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Installation\DatabaseConfiguration;
use App\Domain\Operations\Installation\FreshInstallationSchema;
use App\Domain\Operations\Installation\InstallationAttemptRecord;
use App\Domain\Operations\Installation\InstallationDatabaseOwnership;
use App\Domain\Operations\Installation\ResetIncompleteInstallation;
use PDO;
use Tests\TestCase;

final class IncompleteInstallationResetTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = sys_get_temp_dir().'/waymark-reset-'.bin2hex(random_bytes(8)).'.sqlite';
        touch($this->databasePath);
    }

    protected function tearDown(): void
    {
        @unlink($this->databasePath);

        parent::tearDown();
    }

    public function test_owned_partial_fresh_install_can_be_reset_after_explicit_confirmation(): void
    {
        $attempt = $this->attempt();
        $ownership = new InstallationDatabaseOwnership;
        $ownership->claim($this->configuration(), $attempt);

        $pdo = $this->pdo();
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email VARCHAR(255) NOT NULL)');

        $result = (new ResetIncompleteInstallation($ownership))->handle(
            $this->configuration(),
            $attempt,
            'RESET WAYMARK INSTALLATION',
        );

        self::assertTrue($result->successful);
        self::assertSame([], $this->tables());
    }

    public function test_unknown_table_blocks_reset_even_when_an_attempt_marker_matches(): void
    {
        $attempt = $this->attempt();
        $ownership = new InstallationDatabaseOwnership;
        $ownership->claim($this->configuration(), $attempt);
        $this->pdo()->exec('CREATE TABLE unrelated_customer_records (id INTEGER PRIMARY KEY, private_value TEXT NOT NULL)');

        $result = (new ResetIncompleteInstallation($ownership))->handle(
            $this->configuration(),
            $attempt,
            'RESET WAYMARK INSTALLATION',
        );

        self::assertFalse($result->successful);
        self::assertStringContainsString('cannot prove', $result->message);
        self::assertContains('unrelated_customer_records', $this->tables());
    }

    public function test_known_legacy_partial_migration_state_can_be_reset_without_a_new_attempt_marker(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('CREATE TABLE migrations (id INTEGER PRIMARY KEY AUTOINCREMENT, migration VARCHAR(255) NOT NULL, batch INTEGER NOT NULL)');
        $pdo->exec('CREATE TABLE site_media (id INTEGER PRIMARY KEY, regeneration_cleanup_status VARCHAR(255) NULL)');
        $pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (?, 1)')->execute([
            '2026_08_22_101000_harden_site_media_focal_points',
        ]);

        $result = (new ResetIncompleteInstallation(new InstallationDatabaseOwnership))->handle(
            $this->configuration(),
            null,
            'RESET WAYMARK INSTALLATION',
        );

        self::assertTrue($result->successful);
        self::assertSame([], $this->tables());
    }

    public function test_reset_confirmation_is_required_without_changing_the_database(): void
    {
        $attempt = $this->attempt();
        $ownership = new InstallationDatabaseOwnership;
        $ownership->claim($this->configuration(), $attempt);

        $result = (new ResetIncompleteInstallation($ownership))->handle(
            $this->configuration(),
            $attempt,
            'reset',
        );

        self::assertFalse($result->successful);
        self::assertContains(InstallationDatabaseOwnership::TABLE, $this->tables());
    }

    private function attempt(): InstallationAttemptRecord
    {
        $manifest = FreshInstallationSchema::load(resource_path('installation/fresh-schema.json'));

        return InstallationAttemptRecord::start(
            '01JRESETATTEMPT00000000000',
            'private-ownership-token',
            hash('sha256', 'database-fingerprint'),
            $manifest->migrationSetHash(),
            count($manifest->migrationNames()),
            '2026-08-25T12:00:00+00:00',
        );
    }

    private function configuration(): DatabaseConfiguration
    {
        return new DatabaseConfiguration('sqlite', '', null, $this->databasePath, '', '');
    }

    private function pdo(): PDO
    {
        return new PDO('sqlite:'.$this->databasePath, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    /** @return list<string> */
    private function tables(): array
    {
        return array_values(array_map('strval', $this->pdo()
            ->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
            ->fetchAll(PDO::FETCH_COLUMN)));
    }
}
