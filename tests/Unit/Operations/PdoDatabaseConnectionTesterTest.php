<?php

namespace Tests\Unit\Operations;

use App\Domain\Operations\Installation\DatabaseConfiguration;
use App\Domain\Operations\Installation\PdoDatabaseConnectionTester;
use PDO;
use Tests\TestCase;

final class PdoDatabaseConnectionTesterTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = sys_get_temp_dir().'/waymark-preflight-'.bin2hex(random_bytes(8)).'.sqlite';
        touch($this->databasePath);
    }

    protected function tearDown(): void
    {
        @unlink($this->databasePath);

        parent::tearDown();
    }

    public function test_empty_database_must_pass_the_schema_permission_probe_without_leaving_probe_objects(): void
    {
        $result = (new PdoDatabaseConnectionTester)->test($this->configuration());

        self::assertTrue($result->successful);
        self::assertSame('Database connection and schema permissions verified.', $result->message);
        self::assertSame('empty', $result->state->value);

        $tables = (new PDO('sqlite:'.$this->databasePath))
            ->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")
            ->fetchAll(PDO::FETCH_COLUMN);

        self::assertSame([], $tables);
    }

    public function test_unrelated_existing_table_blocks_fresh_installation_without_mutating_it(): void
    {
        $pdo = new PDO('sqlite:'.$this->databasePath);
        $pdo->exec('CREATE TABLE existing_application_records (id INTEGER PRIMARY KEY, value TEXT NOT NULL)');
        $pdo->exec("INSERT INTO existing_application_records (value) VALUES ('keep me')");

        $result = (new PdoDatabaseConnectionTester)->test($this->configuration());

        self::assertFalse($result->successful);
        self::assertSame('ambiguous', $result->state->value);
        self::assertSame('This database already contains data that Waymark cannot safely use for a fresh installation. Use a new empty database or seek technical assistance.', $result->message);
        self::assertSame('keep me', $pdo->query('SELECT value FROM existing_application_records')->fetchColumn());
    }

    public function test_exact_observed_unrecorded_site_media_ddl_is_classified_as_recoverable_incomplete_waymark_schema(): void
    {
        $pdo = new PDO('sqlite:'.$this->databasePath);
        $this->createMigrationHistory($pdo);
        $pdo->exec('CREATE TABLE site_media (id INTEGER PRIMARY KEY, regeneration_cleanup_status VARCHAR(255) NULL)');
        $pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (?, 1)')->execute([
            '2026_08_22_101000_harden_site_media_focal_points',
        ]);

        $result = (new PdoDatabaseConnectionTester)->test($this->configuration());

        self::assertFalse($result->successful);
        self::assertSame('incomplete', $result->state->value);
        self::assertTrue($result->resetSafe);
        self::assertStringContainsString('incomplete Waymark installation', $result->message);
    }

    public function test_generic_unrecorded_waymark_ddl_is_recoverable_without_hard_coding_one_column(): void
    {
        $pdo = new PDO('sqlite:'.$this->databasePath);
        $this->createMigrationHistory($pdo);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email VARCHAR(255) NOT NULL)');
        $pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (?, 1)')->execute([
            '0001_01_01_000000_create_users_table',
        ]);

        $result = (new PdoDatabaseConnectionTester)->test($this->configuration());

        self::assertSame('incomplete', $result->state->value);
        self::assertTrue($result->resetSafe);
    }

    public function test_unknown_column_keeps_a_waymark_like_database_ambiguous_and_not_resettable(): void
    {
        $pdo = new PDO('sqlite:'.$this->databasePath);
        $this->createMigrationHistory($pdo);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, unrelated_private_value TEXT NULL)');
        $pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (?, 1)')->execute([
            '0001_01_01_000000_create_users_table',
        ]);

        $result = (new PdoDatabaseConnectionTester)->test($this->configuration());

        self::assertSame('ambiguous', $result->state->value);
        self::assertFalse($result->resetSafe);
    }

    private function configuration(): DatabaseConfiguration
    {
        return new DatabaseConfiguration('sqlite', '', null, $this->databasePath, '', '');
    }

    private function createMigrationHistory(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE migrations (id INTEGER PRIMARY KEY AUTOINCREMENT, migration VARCHAR(255) NOT NULL, batch INTEGER NOT NULL)');
    }
}
