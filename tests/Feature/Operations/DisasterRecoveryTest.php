<?php

namespace Tests\Feature\Operations;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Operations\Backups\Actions\CreateBackup;
use App\Domain\Operations\Installation\InstallationState;
use App\Domain\Operations\Models\SiteProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class DisasterRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private string $environmentPath;

    private string $installationPath;

    private string $maintenancePath;

    private string $recoveryDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('backups');
        $this->environmentPath = storage_path('framework/testing/recovery-environment-'.bin2hex(random_bytes(8)));
        file_put_contents($this->environmentPath, "APP_KEY=base64:recovery-key\nAPP_URL=https://source.example\nDB_HOST=source-db\nDB_PORT=3306\nDB_DATABASE=source_waymark\nDB_USERNAME=source_user\nDB_PASSWORD=source-password\nFILESYSTEM_DISK=source-disk\n");
        config()->set('waymark.backups.environment_path', $this->environmentPath);
        config()->set('waymark.backups.restore_environment_path', $this->environmentPath);
        config()->set('waymark.backups.destination_disk', 'backups');
        config()->set('waymark.recovery.token_hash', hash('sha256', 'a-strong-standalone-recovery-token'));
        $this->installationPath = $this->environmentPath.'.installed';
        $this->maintenancePath = $this->environmentPath.'.maintenance';
        $this->recoveryDirectory = $this->environmentPath.'-inbox';
        mkdir($this->recoveryDirectory, 0700, true);
        config()->set('waymark.installation.lock_path', $this->installationPath);
        config()->set('waymark.maintenance.state_path', $this->maintenancePath);
        config()->set('waymark.recovery.archive_directory', $this->recoveryDirectory);
        config()->set('waymark.installation.installed', true);
        $this->app->forgetInstance(InstallationState::class);
    }

    protected function tearDown(): void
    {
        @unlink($this->environmentPath);
        @unlink($this->installationPath);
        @unlink($this->maintenancePath);
        foreach (glob($this->recoveryDirectory.DIRECTORY_SEPARATOR.'*') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($this->recoveryDirectory);
        parent::tearDown();
    }

    public function test_recovery_entry_is_available_without_an_admin_session_and_rejects_wrong_token_generically(): void
    {
        $this->get('/recovery')->assertSuccessful()->assertSee('Disaster recovery');

        $this->post('/recovery', [
            'recovery_token' => 'wrong-token',
            'confirmation' => 'RESTORE WAYMARK',
        ])->assertRedirect()->assertSessionHasErrors('recovery');

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

    public function test_fresh_host_recovery_preserves_host_settings_restores_identity_and_locks_setup(): void
    {
        SiteProfile::query()->create(['group_name' => 'Recovered Wayfarers']);
        User::factory()->create([
            'role' => AccountRole::Administrator,
            'email' => 'recovered-admin@example.test',
            'password' => 'password',
        ]);
        $backup = app(CreateBackup::class)->handle('manual');
        copy(Storage::disk('backups')->path($backup->storage_path), $this->recoveryDirectory.DIRECTORY_SEPARATOR.'large-recovery.zip');

        file_put_contents($this->environmentPath, "APP_KEY=base64:temporary-target-key\nAPP_URL=https://target.example\nDB_HOST=target-db\nDB_PORT=3307\nDB_DATABASE=target_waymark\nDB_USERNAME=target_user\nDB_PASSWORD=target-password\nFILESYSTEM_DISK=local\nCACHE_STORE=file\nSESSION_DRIVER=database\nQUEUE_CONNECTION=sync\n");
        config()->set('waymark.installation.installed', false);
        $this->app->forgetInstance(InstallationState::class);

        $this->post('/recovery', [
            'recovery_token' => 'a-strong-standalone-recovery-token',
            'confirmation' => 'RESTORE WAYMARK',
            'server_archive' => 'large-recovery.zip',
        ])->assertSuccessful()->assertSee('Restore completed');

        $environment = (string) file_get_contents($this->environmentPath);
        $this->assertStringContainsString('APP_KEY=base64:recovery-key', $environment);
        $this->assertStringContainsString('APP_URL=https://target.example', $environment);
        $this->assertStringContainsString('DB_HOST=target-db', $environment);
        $this->assertStringContainsString('DB_PORT=3307', $environment);
        $this->assertStringContainsString('DB_DATABASE=target_waymark', $environment);
        $this->assertStringContainsString('DB_USERNAME=target_user', $environment);
        $this->assertStringContainsString('DB_PASSWORD=target-password', $environment);
        $this->assertStringContainsString('FILESYSTEM_DISK=local', $environment);
        $this->assertFileExists($this->installationPath);

        $this->app->forgetInstance(InstallationState::class);
        $this->assertTrue(app(InstallationState::class)->installed());
        $this->get('/')->assertSuccessful()->assertSee('Recovered Wayfarers');
        $this->get('/setup')->assertNotFound();

        $administrator = User::query()->where('email', 'recovered-admin@example.test')->sole();
        $this->post('/login', ['email' => $administrator->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($administrator);
    }

    public function test_server_archive_recovery_rejects_arbitrary_paths(): void
    {
        file_put_contents(dirname($this->recoveryDirectory).DIRECTORY_SEPARATOR.'outside.zip', 'not a backup');

        $this->post('/recovery', [
            'recovery_token' => 'a-strong-standalone-recovery-token',
            'confirmation' => 'RESTORE WAYMARK',
            'server_archive' => '../outside.zip',
        ])->assertRedirect()->assertSessionHasErrors('recovery');

        @unlink(dirname($this->recoveryDirectory).DIRECTORY_SEPARATOR.'outside.zip');
    }
}
