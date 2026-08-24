<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Backups\Actions\CreateBackup;
use App\Domain\Operations\Maintenance\MaintenanceManager;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Updates\Actions\FinalizeUpdateRollback;
use App\Domain\Operations\Updates\UpdateRuntimeBoundary;
use App\Domain\Operations\Updates\UpdateStateStore;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

final class UpdateRollbackRuntimeTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate:fresh', ['--force' => true]);
        Storage::fake('local');
        Storage::fake('backups');
        $this->directory = storage_path('framework/testing/update-rollback-runtime-'.bin2hex(random_bytes(8)));
        mkdir($this->directory, 0700, true);
        file_put_contents($this->directory.DIRECTORY_SEPARATOR.'.env', "APP_KEY=base64:rollback-source-key\nAPP_URL=https://target.example\n");
        config()->set('waymark.version', '1.0.0');
        config()->set('waymark.backups.environment_path', $this->directory.DIRECTORY_SEPARATOR.'.env');
        config()->set('waymark.backups.restore_environment_path', $this->directory.DIRECTORY_SEPARATOR.'.env');
        config()->set('waymark.backups.destination_disk', 'backups');
        config()->set('waymark.updates.state_path', $this->directory.DIRECTORY_SEPARATOR.'update-state.json');
        config()->set('waymark.maintenance.state_path', $this->directory.DIRECTORY_SEPARATOR.'maintenance.json');
        config()->set('waymark.operations.lock_path', $this->directory.DIRECTORY_SEPARATOR.'operation.lock');
        config()->set('waymark.operations.state_path', $this->directory.DIRECTORY_SEPARATOR.'operation.json');
        config()->set('waymark.operations.journal_path', $this->directory.DIRECTORY_SEPARATOR.'journal.jsonl');
    }

    protected function tearDown(): void
    {
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->clean($this->directory);
        parent::tearDown();
    }

    public function test_database_and_health_rollback_require_a_fresh_old_runtime(): void
    {
        SiteProfile::query()->create(['group_name' => 'Healthy v1 State']);
        Storage::disk('local')->put('documents/v1-policy.pdf', 'v1-private-file');
        $backup = app(CreateBackup::class)->handle('pre-update');
        SiteProfile::query()->update(['group_name' => 'Incompatible v2 State']);
        Storage::disk('local')->delete('documents/v1-policy.pdf');
        app(MaintenanceManager::class)->enable('Waymark is rolling back a failed update.');
        $failedRuntime = app(UpdateRuntimeBoundary::class)->id;
        app(UpdateStateStore::class)->write([
            'status' => 'pending_rollback',
            'rollback_token_hash' => hash('sha256', 'one-time-rollback-token'),
            'failed_runtime_id' => $failedRuntime,
            'rollback_complete' => false,
            'backup' => [
                'status' => 'completed',
                'trigger' => $backup->trigger,
                'storage_disk' => $backup->storage_disk,
                'storage_path' => $backup->storage_path,
                'sha256' => $backup->sha256,
                'size_bytes' => $backup->size_bytes,
                'encrypted' => $backup->encrypted,
            ],
        ]);

        try {
            app(FinalizeUpdateRollback::class)->handle('one-time-rollback-token');
            $this->fail('The failed v2 runtime unexpectedly verified the v1 rollback.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('fresh PHP request', $exception->getMessage());
        }
        $this->assertSame('Incompatible v2 State', SiteProfile::query()->sole()->group_name);
        $this->assertSame('pending_rollback', app(UpdateStateStore::class)->read()['status']);

        $this->app->forgetInstance(UpdateRuntimeBoundary::class);
        $this->get('/updates/rollback?token=one-time-rollback-token')->assertStatus(405);
        $this->post('/updates/rollback', ['token' => 'one-time-rollback-token'])
            ->assertSuccessful()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee('Update rollback completed');

        $state = app(UpdateStateStore::class)->read();
        $this->assertSame('Healthy v1 State', SiteProfile::query()->sole()->group_name);
        Storage::disk('local')->assertExists('documents/v1-policy.pdf');
        $this->assertSame('v1-private-file', Storage::disk('local')->get('documents/v1-policy.pdf'));
        $this->assertSame('update_failed', $state['status']);
        $this->assertTrue($state['rollback_complete']);
        $this->assertNotSame($failedRuntime, $state['rollback_runtime_id']);
        $this->assertTrue(app(MaintenanceManager::class)->active());
        $this->assertNull(app(UpdateStateStore::class)->authorisedRollback('one-time-rollback-token'));
    }

    private function clean(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }
}
