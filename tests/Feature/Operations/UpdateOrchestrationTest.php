<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Backups\Models\BackupRun;
use App\Domain\Operations\Maintenance\MaintenanceManager;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Updates\Actions\ApplyUpdate;
use App\Domain\Operations\Updates\Actions\FinalizeUpdate;
use App\Domain\Operations\Updates\Contracts\ReleasePackageDownloader;
use App\Domain\Operations\Updates\Contracts\UpdateEnvironmentProbe;
use App\Domain\Operations\Updates\Contracts\UpdateRuntime;
use App\Domain\Operations\Updates\UpdateEnvironment;
use App\Domain\Operations\Updates\UpdateRuntimeBoundary;
use App\Domain\Operations\Updates\UpdateStateStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\UpdateSigningFixture;
use Tests\TestCase;
use ZipArchive;

final class UpdateOrchestrationTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    private string $applicationRoot;

    private string $environmentPath;

    private string $packagePath;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('backups');
        $this->directory = storage_path('framework/testing/update-orchestration-'.bin2hex(random_bytes(8)));
        $this->applicationRoot = $this->directory.DIRECTORY_SEPARATOR.'application';
        mkdir($this->applicationRoot, 0700, true);
        $this->writeApplicationFile('VERSION', "1.0.0\n");
        foreach (['artisan', 'bootstrap/app.php', 'public/index.php', 'vendor/autoload.php', 'public/build/manifest.json'] as $required) {
            $this->writeApplicationFile($required, 'old-'.$required);
        }
        $this->writeApplicationFile('app-marker.txt', 'old-marker');
        $this->writeApplicationFile('obsolete.txt', 'remove-me');
        $this->environmentPath = $this->directory.DIRECTORY_SEPARATOR.'.env';
        file_put_contents($this->environmentPath, "APP_KEY=base64:update-test-key\n");
        $this->packagePath = $this->buildPackage();

        config()->set('waymark.version', '1.0.0');
        config()->set('waymark.updates.metadata_url', 'https://updates.example.test/stable.json');
        config()->set('waymark.updates.public_key_base64', UpdateSigningFixture::publicKeyBase64());
        config()->set('waymark.updates.state_path', $this->directory.DIRECTORY_SEPARATOR.'update-state.json');
        config()->set('waymark.updates.application_root', $this->applicationRoot);
        config()->set('waymark.updates.staging_root', $this->directory.DIRECTORY_SEPARATOR.'staging');
        config()->set('waymark.maintenance.state_path', $this->directory.DIRECTORY_SEPARATOR.'maintenance.json');
        config()->set('waymark.operations.lock_path', $this->directory.DIRECTORY_SEPARATOR.'operation.lock');
        config()->set('waymark.operations.state_path', $this->directory.DIRECTORY_SEPARATOR.'operation.json');
        config()->set('waymark.operations.journal_path', $this->directory.DIRECTORY_SEPARATOR.'journal.jsonl');
        config()->set('waymark.backups.environment_path', $this->environmentPath);
        config()->set('waymark.backups.restore_environment_path', $this->environmentPath);
        config()->set('waymark.backups.destination_disk', 'backups');
        $this->app->instance(UpdateEnvironmentProbe::class, new class implements UpdateEnvironmentProbe
        {
            public function capture(): UpdateEnvironment
            {
                return new UpdateEnvironment('8.3.12', ['openssl', 'zip'], 'mysql', '8.4.2', 100000000);
            }
        });
        $source = $this->packagePath;
        $this->app->instance(ReleasePackageDownloader::class, new class($source) implements ReleasePackageDownloader
        {
            public function __construct(private readonly string $source) {}

            public function download(string $url, string $destination): void
            {
                copy($this->source, $destination);
            }
        });
        $this->app->instance(UpdateRuntime::class, new class implements UpdateRuntime
        {
            public function activate(string $version, string $applicationRoot): void {}
        });
        Http::fake(['https://updates.example.test/stable.json' => Http::response($this->signedFeed(), 200)]);
    }

    protected function tearDown(): void
    {
        $this->clean($this->directory);
        parent::tearDown();
    }

    public function test_verified_compatible_update_stops_after_file_activation_for_a_fresh_runtime_request(): void
    {
        SiteProfile::query()->create(['group_name' => 'Update Test Group']);
        $runtime = new class implements UpdateRuntime
        {
            public bool $called = false;

            public function activate(string $version, string $applicationRoot): void
            {
                $this->called = true;
            }
        };
        $this->app->instance(UpdateRuntime::class, $runtime);

        $result = app(ApplyUpdate::class)->handle('UPDATE WAYMARK');

        $this->assertSame('1.2.0', $result->version);
        $this->assertNotSame('', $result->activationToken);
        $this->assertFalse($runtime->called, 'The old in-memory runtime must not activate the new release.');
        $this->assertSame('new-marker', file_get_contents($this->applicationRoot.DIRECTORY_SEPARATOR.'app-marker.txt'));
        $this->assertSame('new-feature', file_get_contents($this->applicationRoot.DIRECTORY_SEPARATOR.'new'.DIRECTORY_SEPARATOR.'feature.php'));
        $this->assertFileDoesNotExist($this->applicationRoot.DIRECTORY_SEPARATOR.'obsolete.txt');
        $this->assertDatabaseHas('backup_runs', ['trigger' => 'pre-update', 'status' => 'completed']);
        $this->assertTrue(app(MaintenanceManager::class)->active());

        $state = app(UpdateStateStore::class)->read();
        $this->assertSame('pending_activation', $state['status']);
        $this->assertSame(hash('sha256', $result->activationToken), $state['activation_token_hash']);
        $this->assertDirectoryExists($state['operation_directory']);
        $this->assertDirectoryExists($state['rollback_directory']);
        $this->assertSame(BackupRun::query()->where('trigger', 'pre-update')->sole()->id, $state['backup_id']);
    }

    public function test_controlled_migration_failure_rolls_back_files_database_and_keeps_maintenance_active(): void
    {
        $profile = SiteProfile::query()->create(['group_name' => 'Before Failed Update']);
        Storage::disk('local')->put('documents/policy.pdf', 'before-update-document');
        $this->app->instance(UpdateRuntime::class, new class implements UpdateRuntime
        {
            public function activate(string $version, string $applicationRoot): void
            {
                SiteProfile::query()->update(['group_name' => 'Partially Migrated']);
                throw new RuntimeException('controlled migration failure');
            }
        });

        $pending = app(ApplyUpdate::class)->handle('UPDATE WAYMARK');
        $state = app(UpdateStateStore::class)->read();
        $this->app->forgetInstance(UpdateRuntimeBoundary::class);

        try {
            app(FinalizeUpdate::class)->handle($pending->activationToken);
            $this->fail('The controlled update failure unexpectedly succeeded.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('rolled back', $exception->getMessage());
        }

        $this->assertSame('old-marker', file_get_contents($this->applicationRoot.DIRECTORY_SEPARATOR.'app-marker.txt'));
        $this->assertSame('remove-me', file_get_contents($this->applicationRoot.DIRECTORY_SEPARATOR.'obsolete.txt'));
        $this->assertDirectoryDoesNotExist($this->applicationRoot.DIRECTORY_SEPARATOR.'new');
        $this->assertSame('Before Failed Update', SiteProfile::query()->sole()->group_name);
        Storage::disk('local')->assertExists('documents/policy.pdf');
        $this->assertTrue(app(MaintenanceManager::class)->active());
        $this->assertSame(0, BackupRun::query()->where('status', 'completed')->count(), 'Database restore returns to the pre-backup audit state.');
        $this->assertDirectoryExists($state['rollback_directory']);
        $this->assertSame('update_failed', app(UpdateStateStore::class)->read()['status']);
    }

    public function test_inexact_confirmation_stops_before_download_or_backup(): void
    {
        $this->expectException(RuntimeException::class);
        try {
            app(ApplyUpdate::class)->handle('update waymark');
        } finally {
            $this->assertDatabaseCount('backup_runs', 0);
            $this->assertSame('old-marker', file_get_contents($this->applicationRoot.DIRECTORY_SEPARATOR.'app-marker.txt'));
        }
    }

    public function test_compatibility_blocker_stops_before_download_or_backup(): void
    {
        $this->app->instance(UpdateEnvironmentProbe::class, new class implements UpdateEnvironmentProbe
        {
            public function capture(): UpdateEnvironment
            {
                return new UpdateEnvironment('8.2.20', ['openssl', 'zip'], 'mysql', '8.4.2', 100000000);
            }
        });

        try {
            app(ApplyUpdate::class)->handle('UPDATE WAYMARK');
            $this->fail('The incompatible update unexpectedly proceeded.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('compatibility blockers', $exception->getMessage());
        }

        $this->assertDatabaseCount('backup_runs', 0);
        $this->assertSame('old-marker', file_get_contents($this->applicationRoot.DIRECTORY_SEPARATOR.'app-marker.txt'));
    }

    public function test_backup_failure_stops_before_maintenance_or_file_swap(): void
    {
        config()->set('waymark.backups.environment_path', $this->environmentPath.'.missing');

        try {
            app(ApplyUpdate::class)->handle('UPDATE WAYMARK');
            $this->fail('The update unexpectedly ignored backup failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Backup creation failed', $exception->getMessage());
        }

        $this->assertDatabaseHas('backup_runs', ['trigger' => 'pre-update', 'status' => 'failed']);
        $this->assertSame('old-marker', file_get_contents($this->applicationRoot.DIRECTORY_SEPARATOR.'app-marker.txt'));
        $this->assertFalse(app(MaintenanceManager::class)->active());
    }

    public function test_file_swap_preflight_conflict_stops_after_backup_without_entering_maintenance(): void
    {
        unlink($this->applicationRoot.DIRECTORY_SEPARATOR.'app-marker.txt');
        mkdir($this->applicationRoot.DIRECTORY_SEPARATOR.'app-marker.txt');

        try {
            app(ApplyUpdate::class)->handle('UPDATE WAYMARK');
            $this->fail('The update unexpectedly ignored a file path conflict.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('conflicts', $exception->getMessage());
        }

        $this->assertDatabaseHas('backup_runs', ['trigger' => 'pre-update', 'status' => 'completed']);
        $this->assertDirectoryExists($this->applicationRoot.DIRECTORY_SEPARATOR.'app-marker.txt');
        $this->assertFalse(app(MaintenanceManager::class)->active());
    }

    public function test_controlled_health_failure_rolls_back_and_does_not_reopen(): void
    {
        SiteProfile::query()->create(['group_name' => 'Before Health Failure']);
        $this->app->instance(UpdateRuntime::class, new class implements UpdateRuntime
        {
            public function activate(string $version, string $applicationRoot): void
            {
                throw new RuntimeException('controlled post-update health failure');
            }
        });

        $pending = app(ApplyUpdate::class)->handle('UPDATE WAYMARK');
        $this->app->forgetInstance(UpdateRuntimeBoundary::class);

        try {
            app(FinalizeUpdate::class)->handle($pending->activationToken);
            $this->fail('The failed health check unexpectedly reopened the site.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('rolled back', $exception->getMessage());
        }

        $this->assertSame('old-marker', file_get_contents($this->applicationRoot.DIRECTORY_SEPARATOR.'app-marker.txt'));
        $this->assertTrue(app(MaintenanceManager::class)->active());
    }

    public function test_fresh_runtime_activation_marks_installed_cleans_rollback_and_reopens(): void
    {
        SiteProfile::query()->create(['group_name' => 'Before Activation']);
        $runtime = new class implements UpdateRuntime
        {
            public bool $called = false;

            public function activate(string $version, string $applicationRoot): void
            {
                $this->called = true;
                SiteProfile::query()->update(['group_name' => 'Activated '.$version]);
            }
        };
        $this->app->instance(UpdateRuntime::class, $runtime);
        $pending = app(ApplyUpdate::class)->handle('UPDATE WAYMARK');
        $pendingState = app(UpdateStateStore::class)->read();
        $this->app->forgetInstance(UpdateRuntimeBoundary::class);

        $result = app(FinalizeUpdate::class)->handle($pending->activationToken);

        $this->assertTrue($runtime->called);
        $this->assertSame('1.2.0', $result->version);
        $this->assertSame('Activated 1.2.0', SiteProfile::query()->sole()->group_name);
        $this->assertSame('installed', app(UpdateStateStore::class)->read()['status']);
        $this->assertDirectoryDoesNotExist($pendingState['operation_directory']);
        $this->assertFalse(app(MaintenanceManager::class)->active());
    }

    public function test_activation_refuses_the_runtime_that_started_the_update_without_consuming_pending_state(): void
    {
        SiteProfile::query()->create(['group_name' => 'Runtime Boundary']);
        $pending = app(ApplyUpdate::class)->handle('UPDATE WAYMARK');

        try {
            app(FinalizeUpdate::class)->handle($pending->activationToken);
            $this->fail('The initiating runtime unexpectedly activated its own replacement.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('fresh PHP request', $exception->getMessage());
        }

        $this->assertSame('pending_activation', app(UpdateStateStore::class)->read()['status']);
        $this->assertSame('new-marker', file_get_contents($this->applicationRoot.DIRECTORY_SEPARATOR.'app-marker.txt'));
        $this->assertTrue(app(MaintenanceManager::class)->active());
    }

    /** @return array<string, mixed> */
    private function signedFeed(): array
    {
        $payload = [
            'schema' => 1, 'channel' => 'stable', 'version' => '1.2.0', 'published_at' => '2026-08-24T09:00:00Z',
            'security_release' => true, 'summary' => 'Verified test release.', 'release_notes' => ['Controlled fixture.'],
            'package_url' => 'https://updates.example.test/release.zip', 'package_sha256' => hash_file('sha256', $this->packagePath),
            'package_size_bytes' => filesize($this->packagePath),
            'requirements' => ['php' => '8.3.0', 'extensions' => ['openssl', 'zip'], 'database' => ['mysql' => '8.0.0', 'mariadb' => '10.6.0'], 'disk_free_bytes' => 1],
        ];
        openssl_sign(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $signature, UpdateSigningFixture::privateKey(), OPENSSL_ALGO_SHA256);

        return ['payload' => $payload, 'signature' => base64_encode($signature)];
    }

    private function buildPackage(): string
    {
        $path = $this->directory.DIRECTORY_SEPARATOR.'release.zip';
        $files = ['VERSION' => "1.2.0\n", 'artisan' => 'new-artisan', 'bootstrap/app.php' => 'new-bootstrap', 'public/index.php' => 'new-front', 'vendor/autoload.php' => 'new-autoload', 'public/build/manifest.json' => '{}', 'app-marker.txt' => 'new-marker', 'new/feature.php' => 'new-feature'];
        $manifest = ['format' => 1, 'version' => '1.2.0', 'files' => [], 'deletes' => ['obsolete.txt']];
        $archive = new ZipArchive;
        $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($files as $file => $contents) {
            $manifest['files'][] = ['path' => $file, 'sha256' => hash('sha256', $contents), 'size_bytes' => strlen($contents)];
            $archive->addFromString('application/'.$file, $contents);
        }
        $archive->addFromString('release-manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $archive->close();

        return $path;
    }

    private function writeApplicationFile(string $path, string $contents): void
    {
        $target = $this->applicationRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0700, true);
        }
        file_put_contents($target, $contents);
    }

    private function clean(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }
}
