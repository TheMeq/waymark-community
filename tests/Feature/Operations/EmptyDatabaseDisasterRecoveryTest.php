<?php

namespace Tests\Feature\Operations;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Operations\Backups\Actions\CreateBackup;
use App\Domain\Operations\Installation\InstallationState;
use App\Domain\Operations\Models\SiteProfile;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

final class EmptyDatabaseDisasterRecoveryTest extends TestCase
{
    private string $environmentPath;

    private string $installationPath;

    private string $maintenancePath;

    private string $recoveryDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate:fresh', ['--force' => true]);
        Storage::fake('local');
        Storage::fake('backups');

        $token = bin2hex(random_bytes(8));
        $this->environmentPath = storage_path('framework/testing/empty-recovery-environment-'.$token);
        $this->installationPath = $this->environmentPath.'.installed';
        $this->maintenancePath = $this->environmentPath.'.maintenance';
        $this->recoveryDirectory = $this->environmentPath.'-inbox';
        mkdir($this->recoveryDirectory, 0700, true);

        file_put_contents($this->environmentPath, "APP_KEY=base64:source-recovery-key\nAPP_URL=https://source.example\nDB_HOST=source-db\nDB_PORT=3306\nDB_DATABASE=source_waymark\nDB_USERNAME=source_user\nDB_PASSWORD=source-password\nFILESYSTEM_DISK=source-disk\n");
        config()->set('waymark.version', '1.0.0');
        config()->set('waymark.backups.environment_path', $this->environmentPath);
        config()->set('waymark.backups.restore_environment_path', $this->environmentPath);
        config()->set('waymark.backups.destination_disk', 'backups');
        config()->set('waymark.recovery.token_hash', hash('sha256', 'empty-database-recovery-token'));
        config()->set('waymark.recovery.archive_directory', $this->recoveryDirectory);
        config()->set('waymark.installation.lock_path', $this->installationPath);
        config()->set('waymark.maintenance.state_path', $this->maintenancePath);
        config()->set('waymark.installation.installed', false);
        $this->app->forgetInstance(InstallationState::class);
    }

    protected function tearDown(): void
    {
        Artisan::call('migrate:fresh', ['--force' => true]);
        @unlink($this->environmentPath);
        @unlink($this->installationPath);
        @unlink($this->maintenancePath);
        foreach (glob($this->recoveryDirectory.DIRECTORY_SEPARATOR.'*') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($this->recoveryDirectory);
        parent::tearDown();
    }

    public function test_same_release_backup_recovers_a_genuinely_empty_database(): void
    {
        SiteProfile::query()->create(['group_name' => 'Recovered Empty Database Group']);
        User::factory()->create([
            'role' => AccountRole::Administrator,
            'email' => 'empty-db-admin@example.test',
            'password' => 'password',
        ]);
        Storage::disk('local')->put('community-photos/recovered.jpg', 'recovered-private-media');
        $backup = app(CreateBackup::class)->handle('manual');
        copy(Storage::disk('backups')->path($backup->storage_path), $this->recoveryDirectory.DIRECTORY_SEPARATOR.'fresh-host.zip');

        Schema::dropAllTables();
        $this->assertFalse(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasTable('migrations'));
        Storage::disk('local')->delete('community-photos/recovered.jpg');
        file_put_contents($this->environmentPath, "APP_KEY=base64:temporary-target-key\nAPP_URL=https://target.example\nDB_HOST=target-db\nDB_PORT=3307\nDB_DATABASE=target_waymark\nDB_USERNAME=target_user\nDB_PASSWORD=target-password\nFILESYSTEM_DISK=local\nCACHE_STORE=file\nSESSION_DRIVER=database\nQUEUE_CONNECTION=sync\n");

        $this->post('/recovery', [
            'recovery_token' => 'empty-database-recovery-token',
            'confirmation' => 'RESTORE WAYMARK',
            'server_archive' => 'fresh-host.zip',
        ])->assertSuccessful()->assertSee('Restore completed');

        $this->assertTrue(Schema::hasTable('migrations'));
        $this->assertSame('Recovered Empty Database Group', SiteProfile::query()->sole()->group_name);
        Storage::disk('local')->assertExists('community-photos/recovered.jpg');
        $this->assertSame('recovered-private-media', Storage::disk('local')->get('community-photos/recovered.jpg'));
        $environment = (string) file_get_contents($this->environmentPath);
        $this->assertStringContainsString('APP_KEY=base64:source-recovery-key', $environment);
        $this->assertStringContainsString('APP_URL=https://target.example', $environment);
        $this->assertStringContainsString('DB_HOST=target-db', $environment);
        $this->assertStringContainsString('DB_PORT=3307', $environment);
        $this->assertStringContainsString('DB_DATABASE=target_waymark', $environment);
        $this->assertStringContainsString('DB_USERNAME=target_user', $environment);
        $this->assertStringContainsString('DB_PASSWORD=target-password', $environment);
        $this->assertStringContainsString('FILESYSTEM_DISK=local', $environment);
        $this->assertFileExists($this->installationPath);

        $this->app->forgetInstance(InstallationState::class);
        $this->get('/')->assertSuccessful()->assertSee('Recovered Empty Database Group');
        $this->get('/setup')->assertNotFound();
        $this->post('/login', ['email' => 'empty-db-admin@example.test', 'password' => 'password']);
        $this->assertAuthenticated();
    }

    #[DataProvider('differentReleaseVersions')]
    public function test_different_release_archive_is_rejected_before_target_schema_is_rebuilt(string $version): void
    {
        SiteProfile::query()->create(['group_name' => 'Source Group']);
        $backup = app(CreateBackup::class)->handle('manual');
        $archivePath = Storage::disk('backups')->path($backup->storage_path);
        $archive = new ZipArchive;
        $this->assertTrue($archive->open($archivePath));
        $manifest = json_decode($archive->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $manifest['waymark_version'] = $version;
        $archive->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $archive->close();
        copy($archivePath, $this->recoveryDirectory.DIRECTORY_SEPARATOR.'different-release.zip');

        Schema::dropAllTables();
        Schema::create('target_schema_marker', function (Blueprint $table): void {
            $table->id();
        });

        $this->post('/recovery', [
            'recovery_token' => 'empty-database-recovery-token',
            'confirmation' => 'RESTORE WAYMARK',
            'server_archive' => 'different-release.zip',
        ])->assertRedirect()->assertSessionHasErrors('recovery');

        $this->assertTrue(Schema::hasTable('target_schema_marker'));
        $this->assertFalse(Schema::hasTable('migrations'));
    }

    public function test_invalid_archive_is_rejected_without_touching_target_schema(): void
    {
        file_put_contents($this->recoveryDirectory.DIRECTORY_SEPARATOR.'invalid.zip', 'not a Waymark backup');
        Schema::dropAllTables();
        Schema::create('target_schema_marker', function (Blueprint $table): void {
            $table->id();
        });

        $this->post('/recovery', [
            'recovery_token' => 'empty-database-recovery-token',
            'confirmation' => 'RESTORE WAYMARK',
            'server_archive' => 'invalid.zip',
        ])->assertRedirect()->assertSessionHasErrors('recovery');

        $this->assertTrue(Schema::hasTable('target_schema_marker'));
        $this->assertFalse(Schema::hasTable('migrations'));
    }

    public static function differentReleaseVersions(): array
    {
        return [
            'older release' => ['0.9.0'],
            'newer release' => ['2.0.0'],
        ];
    }
}
