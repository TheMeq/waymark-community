<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Backups\Actions\CreateBackup;
use App\Domain\Operations\Backups\Actions\RestoreBackup;
use App\Domain\Operations\Backups\Contracts\RestoreHealthProbe;
use App\Domain\Operations\Installation\InstallationState;
use App\Domain\Operations\Maintenance\MaintenanceManager;
use App\Domain\Operations\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

final class BackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    private string $environmentPath;

    private string $installationPath;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('backups');
        $this->environmentPath = storage_path('framework/testing/restore-environment-'.bin2hex(random_bytes(8)));
        file_put_contents($this->environmentPath, "APP_KEY=base64:original-key\nAPP_URL=https://source.example\nDB_HOST=source-db\nFILESYSTEM_DISK=source-disk\n");
        config()->set('waymark.backups.environment_path', $this->environmentPath);
        config()->set('waymark.backups.restore_environment_path', $this->environmentPath);
        config()->set('waymark.backups.destination_disk', 'backups');
        config()->set('waymark.maintenance.state_path', $this->environmentPath.'.maintenance');
        $this->installationPath = $this->environmentPath.'.installed';
        config()->set('waymark.installation.lock_path', $this->installationPath);
        $this->app->forgetInstance(InstallationState::class);
    }

    protected function tearDown(): void
    {
        @unlink($this->environmentPath);
        @unlink($this->environmentPath.'.maintenance');
        @unlink($this->installationPath);
        parent::tearDown();
    }

    public function test_verified_backup_restores_database_private_files_and_configuration(): void
    {
        $profile = SiteProfile::query()->create(['group_name' => 'Original Ramblers']);
        Storage::disk('local')->put('community-photos/original.jpg', 'original-image');
        $backup = app(CreateBackup::class)->handle('manual', 'portable passphrase');

        $profile->update(['group_name' => 'Changed Group']);
        Storage::disk('local')->delete('community-photos/original.jpg');
        Storage::disk('local')->put('community-photos/new.jpg', 'new-image');
        file_put_contents($this->environmentPath, "APP_KEY=base64:changed-key\nAPP_URL=https://target.example\nDB_HOST=target-db\nFILESYSTEM_DISK=local\n");

        $safety = app(CreateBackup::class)->handle('pre-restore');
        app(RestoreBackup::class)->fromRunWithSafetyBackup($backup, $safety, 'RESTORE WAYMARK', 'portable passphrase');

        $this->assertSame('Original Ramblers', SiteProfile::query()->sole()->group_name);
        Storage::disk('local')->assertExists('community-photos/original.jpg');
        Storage::disk('local')->assertMissing('community-photos/new.jpg');
        $restoredEnvironment = (string) file_get_contents($this->environmentPath);
        $this->assertStringContainsString('APP_KEY=base64:original-key', $restoredEnvironment);
        $this->assertStringContainsString('APP_URL=https://target.example', $restoredEnvironment);
        $this->assertStringContainsString('DB_HOST=target-db', $restoredEnvironment);
        $this->assertStringContainsString('FILESYSTEM_DISK=local', $restoredEnvironment);
        $this->assertFalse(app(MaintenanceManager::class)->active());
    }

    public function test_restore_refuses_inexact_destructive_confirmation(): void
    {
        $profile = SiteProfile::query()->create(['group_name' => 'Keep This Group']);
        $backup = app(CreateBackup::class)->handle('manual');

        try {
            app(RestoreBackup::class)->assertRestorableRun($backup, 'restore waymark');
            $this->fail('The restore unexpectedly accepted an inexact confirmation.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('confirmation', $exception->getMessage());
        }

        $this->assertSame('Keep This Group', $profile->fresh()->group_name);
    }

    #[DataProvider('incompatibleBackupVersions')]
    public function test_different_release_backup_is_rejected_before_safety_backup_or_maintenance(string $backupVersion): void
    {
        config()->set('waymark.version', '1.0.0');
        $profile = SiteProfile::query()->create(['group_name' => 'Current Group']);
        $backup = app(CreateBackup::class)->handle('manual');
        $path = Storage::disk('backups')->path($backup->storage_path);
        $archive = new ZipArchive;
        $this->assertTrue($archive->open($path));
        $manifest = json_decode($archive->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $manifest['waymark_version'] = $backupVersion;
        $archive->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $archive->close();

        try {
            app(RestoreBackup::class)->restoreFile($path, 'RESTORE WAYMARK');
            $this->fail('The different-release backup unexpectedly restored.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('matching Waymark release', $exception->getMessage());
        }

        $this->assertSame('Current Group', $profile->fresh()->group_name);
        $this->assertFalse(app(MaintenanceManager::class)->active());
        $this->assertDatabaseMissing('backup_runs', ['trigger' => 'pre-restore']);
    }

    public static function incompatibleBackupVersions(): array
    {
        return [
            'older release' => ['0.9.0'],
            'newer release' => ['99.0.0'],
        ];
    }

    public function test_interrupted_restore_uses_safety_backup_and_keeps_maintenance_active(): void
    {
        $profile = SiteProfile::query()->create(['group_name' => 'Target Backup State']);
        $target = app(CreateBackup::class)->handle('manual');
        $profile->update(['group_name' => 'Current Safe State']);
        Storage::disk('local')->put('documents/current.pdf', 'current-document');
        $safety = app(CreateBackup::class)->handle('pre-restore');
        $this->app->instance(RestoreHealthProbe::class, new class implements RestoreHealthProbe
        {
            private int $attempts = 0;

            public function assertHealthy(): void
            {
                $this->attempts++;
                if ($this->attempts === 1) {
                    throw new RuntimeException('controlled restore interruption');
                }
            }
        });

        try {
            app(RestoreBackup::class)->fromRunWithSafetyBackup($target, $safety, 'RESTORE WAYMARK');
            $this->fail('The interrupted restore unexpectedly succeeded.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('rolled back', $exception->getMessage());
        }

        $this->assertSame('Current Safe State', SiteProfile::query()->sole()->group_name);
        Storage::disk('local')->assertExists('documents/current.pdf');
        $this->assertTrue(app(MaintenanceManager::class)->active());
    }
}
