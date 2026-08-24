<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Backups\Actions\CreateBackup;
use App\Domain\Operations\Installation\InstallationState;
use App\Domain\Operations\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class DisasterRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private string $environmentPath;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('backups');
        $this->environmentPath = storage_path('framework/testing/recovery-environment-'.bin2hex(random_bytes(8)));
        file_put_contents($this->environmentPath, "APP_KEY=base64:recovery-key\n");
        config()->set('waymark.backups.environment_path', $this->environmentPath);
        config()->set('waymark.backups.restore_environment_path', $this->environmentPath);
        config()->set('waymark.backups.destination_disk', 'backups');
        config()->set('waymark.recovery.token_hash', hash('sha256', 'a-strong-standalone-recovery-token'));
        config()->set('waymark.installation.installed', true);
        $this->app->forgetInstance(InstallationState::class);
    }

    protected function tearDown(): void
    {
        @unlink($this->environmentPath);
        parent::tearDown();
    }

    public function test_recovery_entry_is_available_without_an_admin_session_and_rejects_wrong_token_generically(): void
    {
        $this->get('/recovery')->assertSuccessful()->assertSee('Disaster recovery');

        $this->post('/recovery', [
            'recovery_token' => 'wrong-token',
            'confirmation' => 'RESTORE WAYMARK',
        ])->assertRedirect('/recovery')->assertSessionHasErrors('recovery');

        $this->assertArrayNotHasKey('recovery_token', session()->getOldInput());
        $this->assertArrayNotHasKey('passphrase', session()->getOldInput());
    }

    public function test_valid_recovery_token_can_restore_an_uploaded_verified_archive(): void
    {
        $profile = SiteProfile::query()->create(['group_name' => 'Recoverable Group']);
        $backup = app(CreateBackup::class)->handle('manual');
        $archivePath = Storage::disk('backups')->path($backup->storage_path);
        $upload = new UploadedFile($archivePath, 'waymark-backup.zip', 'application/zip', null, true);
        $profile->update(['group_name' => 'Broken State']);

        $this->post('/recovery', [
            'recovery_token' => 'a-strong-standalone-recovery-token',
            'confirmation' => 'RESTORE WAYMARK',
            'backup' => $upload,
        ])->assertSuccessful()->assertSee('Restore completed');

        $this->assertSame('Recoverable Group', SiteProfile::query()->sole()->group_name);
    }
}
