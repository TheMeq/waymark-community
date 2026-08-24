<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Backups\Actions\CreateBackup;
use App\Domain\Operations\Backups\BackupVerifier;
use App\Domain\Operations\Backups\Contracts\BackupCapacityProbe;
use App\Domain\Operations\Backups\Models\BackupRun;
use App\Domain\Operations\Maintenance\MaintenanceManager;
use App\Domain\Operations\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

final class BackupCreationTest extends TestCase
{
    use RefreshDatabase;

    private string $environmentPath;

    private string $maintenancePath;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('backups');
        $this->environmentPath = storage_path('framework/testing/backup-environment-'.bin2hex(random_bytes(8)));
        file_put_contents($this->environmentPath, "APP_KEY=base64:restore-key\nDB_DATABASE=waymark\n");
        config()->set('waymark.backups.environment_path', $this->environmentPath);
        config()->set('waymark.backups.destination_disk', 'backups');
        $this->maintenancePath = $this->environmentPath.'.maintenance';
        config()->set('waymark.maintenance.state_path', $this->maintenancePath);
    }

    protected function tearDown(): void
    {
        if (is_file($this->environmentPath)) {
            unlink($this->environmentPath);
        }
        @unlink($this->maintenancePath);
        parent::tearDown();
    }

    public function test_manual_backup_contains_database_private_media_configuration_and_manifest(): void
    {
        SiteProfile::query()->create(['group_name' => 'Peak Pathfinders']);
        Storage::disk('local')->put('community-photos/example/large.jpg', 'image-data');

        $backup = app(CreateBackup::class)->handle('manual');

        $this->assertSame('completed', $backup->status);
        $this->assertSame('backups', $backup->storage_disk);
        Storage::disk('backups')->assertExists($backup->storage_path);
        $this->assertNotNull($backup->sha256);

        $archive = new ZipArchive;
        $this->assertTrue($archive->open(Storage::disk('backups')->path($backup->storage_path)));
        $this->assertNotFalse($archive->locateName('manifest.json'));
        $this->assertNotFalse($archive->locateName('database.jsonl'));
        $this->assertNotFalse($archive->locateName('configuration/restoration.env'));
        $this->assertFalse($archive->locateName('configuration/.env'));
        $this->assertNotFalse($archive->locateName('private/community-photos/example/large.jpg'));
        $this->assertStringContainsString('site_profiles', (string) $archive->getFromName('database.jsonl'));
        $archive->close();
    }

    public function test_backup_can_stream_to_an_s3_compatible_disk_without_changing_the_local_default(): void
    {
        Storage::fake('s3-backups');
        config()->set('waymark.backups.destination_disk', 's3-backups');

        $backup = app(CreateBackup::class)->handle('manual');

        $this->assertSame('s3-backups', $backup->storage_disk);
        Storage::disk('s3-backups')->assertExists($backup->storage_path);
        $this->assertSame(1, BackupRun::query()->where('status', 'completed')->count());
    }

    public function test_backup_is_not_marked_complete_when_restoration_configuration_is_missing(): void
    {
        config()->set('waymark.backups.environment_path', $this->environmentPath.'.missing');

        try {
            app(CreateBackup::class)->handle('manual');
            $this->fail('The incomplete backup was unexpectedly accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Backup creation failed', $exception->getMessage());
        }

        $this->assertDatabaseHas('backup_runs', ['status' => 'failed']);
        $this->assertDatabaseMissing('backup_runs', ['status' => 'completed']);
    }

    public function test_backup_refuses_known_insufficient_local_staging_space_before_export(): void
    {
        $this->app->instance(BackupCapacityProbe::class, new class implements BackupCapacityProbe
        {
            public function availableBytes(): ?int
            {
                return 1024;
            }
        });

        try {
            app(CreateBackup::class)->handle('manual');
            $this->fail('The backup unexpectedly ignored a known low-space condition.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Backup creation failed', $exception->getMessage());
        }

        $this->assertDatabaseHas('backup_runs', ['status' => 'failed']);
        Storage::disk('backups')->assertDirectoryEmpty('/');
    }

    public function test_manual_backup_start_is_bounded_and_worker_steps_complete_the_verified_archive(): void
    {
        Storage::disk('local')->put('community-photos/example/one.jpg', 'one');
        Storage::disk('local')->put('community-photos/example/two.jpg', 'two');

        $backup = app(CreateBackup::class)->start('manual');

        $this->assertSame('queued', $backup->status);
        $this->assertTrue(app(MaintenanceManager::class)->active(), 'Writes must remain frozen across bounded backup steps.');
        $this->assertNull($backup->storage_path);
        Storage::disk('backups')->assertDirectoryEmpty('/');

        do {
            $backup = app(CreateBackup::class)->advance($backup->fresh(), 1);
        } while (in_array($backup->status, ['queued', 'running'], true));

        $this->assertSame('completed', $backup->status);
        $this->assertFalse(app(MaintenanceManager::class)->active());
        Storage::disk('backups')->assertExists($backup->storage_path);
        app(BackupVerifier::class)->open(
            Storage::disk('backups')->path($backup->storage_path),
        )->cleanup();
    }

    public function test_media_changed_after_snapshot_start_cannot_be_recorded_as_a_verified_backup(): void
    {
        Storage::disk('local')->put('community-photos/example/changing.jpg', 'snapshot-version');
        $backup = app(CreateBackup::class)->start('manual');
        $backup = app(CreateBackup::class)->advance($backup);
        Storage::disk('local')->put('community-photos/example/changing.jpg', 'changed-after-snapshot');

        try {
            app(CreateBackup::class)->advance($backup);
            $this->fail('A media mutation during the consistency window unexpectedly completed.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Backup creation failed', $exception->getMessage());
        }

        $this->assertSame('failed', $backup->fresh()->status);
        $this->assertTrue((bool) $backup->fresh()->retryable);
        $this->assertFalse(app(MaintenanceManager::class)->active(), 'A failed run ends its write-freeze but remains explicitly retryable.');
        Storage::disk('backups')->assertDirectoryEmpty('/');
    }

    public function test_failed_backup_can_restart_from_a_clean_snapshot_boundary(): void
    {
        $backup = app(CreateBackup::class)->start('manual');
        unlink($this->environmentPath);
        try {
            app(CreateBackup::class)->advance($backup);
        } catch (RuntimeException) {
        }
        file_put_contents($this->environmentPath, "APP_KEY=base64:restore-key\nDB_DATABASE=waymark\n");

        $retry = app(CreateBackup::class)->retry($backup->fresh());

        $this->assertSame('queued', $retry->status);
        $this->assertFalse($retry->retryable);
        $this->assertTrue(app(MaintenanceManager::class)->active());
    }

    public function test_only_one_consistency_window_can_be_active_at_a_time(): void
    {
        app(CreateBackup::class)->start('manual');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already in progress');

        app(CreateBackup::class)->start('scheduled');
    }
}
