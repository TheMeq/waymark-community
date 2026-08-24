<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Backups\Actions\CreateBackup;
use App\Domain\Operations\Backups\Actions\RestoreBackup;
use App\Domain\Operations\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

final class BackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    private string $environmentPath;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('backups');
        $this->environmentPath = storage_path('framework/testing/restore-environment-'.bin2hex(random_bytes(8)));
        file_put_contents($this->environmentPath, "APP_KEY=base64:original-key\n");
        config()->set('waymark.backups.environment_path', $this->environmentPath);
        config()->set('waymark.backups.restore_environment_path', $this->environmentPath);
        config()->set('waymark.backups.destination_disk', 'backups');
    }

    protected function tearDown(): void
    {
        @unlink($this->environmentPath);
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
        file_put_contents($this->environmentPath, "APP_KEY=base64:changed-key\n");

        app(RestoreBackup::class)->fromRun($backup, 'RESTORE WAYMARK', 'portable passphrase');

        $this->assertSame('Original Ramblers', SiteProfile::query()->sole()->group_name);
        Storage::disk('local')->assertExists('community-photos/original.jpg');
        Storage::disk('local')->assertMissing('community-photos/new.jpg');
        $this->assertSame("APP_KEY=base64:original-key\n", file_get_contents($this->environmentPath));
    }

    public function test_restore_refuses_inexact_destructive_confirmation(): void
    {
        $profile = SiteProfile::query()->create(['group_name' => 'Keep This Group']);
        $backup = app(CreateBackup::class)->handle('manual');

        try {
            app(RestoreBackup::class)->fromRun($backup, 'restore waymark');
            $this->fail('The restore unexpectedly accepted an inexact confirmation.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('confirmation', $exception->getMessage());
        }

        $this->assertSame('Keep This Group', $profile->fresh()->group_name);
    }
}
