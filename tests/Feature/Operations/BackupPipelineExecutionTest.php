<?php

namespace Tests\Feature\Operations;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Operations\Backups\Actions\CreateBackup;
use App\Domain\Operations\Backups\Models\BackupRun;
use App\Domain\Operations\Scheduling\WaymarkFallbackWorkload;
use App\Filament\Pages\SystemHealth;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

final class BackupPipelineExecutionTest extends TestCase
{
    use RefreshDatabase;

    private string $environmentPath;

    private string $maintenancePath;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('backups');
        $this->environmentPath = storage_path('framework/testing/backup-pipeline-'.bin2hex(random_bytes(8)).'.env');
        file_put_contents($this->environmentPath, "APP_KEY=base64:pipeline-key\n");
        config()->set('waymark.backups.environment_path', $this->environmentPath);
        config()->set('waymark.backups.destination_disk', 'backups');
        $this->maintenancePath = $this->environmentPath.'.maintenance';
        config()->set('waymark.maintenance.state_path', $this->maintenancePath);
    }

    protected function tearDown(): void
    {
        @unlink($this->environmentPath);
        @unlink($this->maintenancePath);
        parent::tearDown();
    }

    public function test_admin_create_backup_only_queues_bounded_work(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);

        Livewire::actingAs($administrator)->test(SystemHealth::class)->call('createBackup');

        $this->assertDatabaseHas('backup_runs', ['trigger' => 'manual', 'status' => 'queued', 'stage' => 'snapshot']);
        Storage::disk('backups')->assertDirectoryEmpty('/');
    }

    public function test_scheduled_and_manual_runs_use_the_same_advance_pipeline(): void
    {
        Artisan::call('waymark:create-backup');
        $scheduled = BackupRun::query()->where('trigger', 'scheduled')->sole();
        Artisan::call('waymark:advance-backups');
        $scheduled->refresh();
        $this->assertSame('copy_private', $scheduled->stage);

        do {
            $scheduled = app(CreateBackup::class)->advance($scheduled);
        } while (in_array($scheduled->status, ['queued', 'running'], true));

        $manual = app(CreateBackup::class)->start('manual');
        $manual = app(CreateBackup::class)->advance($manual);

        $this->assertSame('copy_private', $manual->stage);
    }

    public function test_no_cron_fallback_advances_one_pending_backup_step(): void
    {
        $backup = app(CreateBackup::class)->start('manual');

        $handled = app(WaymarkFallbackWorkload::class)->run();

        $this->assertSame(1, $handled['backup_steps']);
        $this->assertSame('copy_private', $backup->fresh()->stage);
    }

    public function test_authenticated_no_cron_controls_remain_available_during_the_backup_write_freeze(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $backup = app(CreateBackup::class)->start('manual');

        $this->actingAs($administrator)
            ->get('/admin/system-health')
            ->assertSuccessful();

        $this->post('/admin/system-health/backups/'.$backup->id.'/advance')
            ->assertRedirect('/admin/system-health');

        $this->assertSame('copy_private', $backup->fresh()->stage);
    }
}
