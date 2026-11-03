<?php

namespace Tests\Unit\Operations;

use App\Domain\Operations\Installation\InstallationAttemptRecord;
use App\Domain\Operations\Installation\InstallationAttemptStatus;
use App\Domain\Operations\Installation\InstallationAttemptStore;
use App\Domain\Operations\Installation\InstallationStage;
use PHPUnit\Framework\TestCase;

final class InstallationAttemptStoreTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir().'/waymark-attempt-'.bin2hex(random_bytes(8)).'.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        @unlink($this->path.'.tmp');

        parent::tearDown();
    }

    public function test_attempt_progress_survives_a_new_store_instance_without_setup_credentials(): void
    {
        $record = InstallationAttemptRecord::start(
            id: '01JTESTATTEMPT000000000000',
            ownershipToken: 'server-generated-ownership-token',
            connectionFingerprint: hash('sha256', 'database-identity'),
            migrationSetHash: hash('sha256', 'migration-set'),
            totalMigrations: 57,
            now: '2026-08-25T12:00:00+00:00',
        )->advance(InstallationStage::PreparingDatabaseSchema, 9, '2026-08-25T12:01:00+00:00');

        (new InstallationAttemptStore($this->path))->save($record);
        $loaded = (new InstallationAttemptStore($this->path))->load();

        self::assertNotNull($loaded);
        self::assertSame('01JTESTATTEMPT000000000000', $loaded->id);
        self::assertSame(InstallationAttemptStatus::Running, $loaded->status);
        self::assertSame(InstallationStage::PreparingDatabaseSchema, $loaded->stage);
        self::assertSame(9, $loaded->migrationIndex);
        self::assertSame(57, $loaded->totalMigrations);

        $contents = (string) file_get_contents($this->path);
        self::assertStringNotContainsString('DB_PASSWORD', $contents);
        self::assertStringNotContainsString('SMTP', $contents);
        self::assertStringNotContainsString('recovery', strtolower($contents));
        self::assertStringNotContainsString('APP_KEY', $contents);
    }

    public function test_failed_attempt_keeps_only_safe_diagnostic_state_for_resume(): void
    {
        $record = InstallationAttemptRecord::start(
            '01JTESTATTEMPT000000000001',
            'ownership-token',
            'fingerprint',
            'migration-hash',
            57,
            '2026-08-25T12:00:00+00:00',
        )->fail(
            category: 'database_schema_installation_failed',
            diagnosticId: 'WM-7FK2Q9',
            message: 'Database schema installation failed.',
            changed: true,
            now: '2026-08-25T12:02:00+00:00',
        );

        $store = new InstallationAttemptStore($this->path);
        $store->save($record);
        $loaded = $store->load();

        self::assertSame(InstallationAttemptStatus::Failed, $loaded?->status);
        self::assertSame('WM-7FK2Q9', $loaded?->diagnosticId);
        self::assertTrue($loaded?->changed);
        self::assertSame('Database schema installation failed.', $loaded?->message);
    }

    public function test_retry_clears_stale_failure_presentation_state(): void
    {
        $retried = InstallationAttemptRecord::start(
            '01JTESTATTEMPT000000000002',
            'ownership-token',
            'fingerprint',
            'migration-hash',
            57,
            '2026-08-25T12:00:00+00:00',
        )->fail(
            category: 'configuration_file_could_not_be_written',
            diagnosticId: 'WM-OLDFAIL',
            message: 'Waymark could not write the configuration file.',
            changed: false,
            now: '2026-08-25T12:01:00+00:00',
        )->retry('2026-08-25T12:02:00+00:00');

        self::assertSame(InstallationAttemptStatus::Running, $retried->status);
        self::assertNull($retried->diagnosticId);
        self::assertNull($retried->failureCategory);
        self::assertSame('Waymark is ready to resume this saved installation.', $retried->message);
        self::assertStringNotContainsString('fail', strtolower($retried->message));
        self::assertFalse($retried->changed);
    }
}
