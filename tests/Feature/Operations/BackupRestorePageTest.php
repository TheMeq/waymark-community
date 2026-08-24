<?php

namespace Tests\Feature\Operations;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Security\SensitiveActionAssurance;
use App\Domain\Operations\Backups\Actions\CreateBackup;
use App\Domain\Operations\Backups\Models\BackupRun;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Scheduling\WaymarkFallbackWorkload;
use App\Filament\Pages\BackupRestore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

final class BackupRestorePageTest extends TestCase
{
    use RefreshDatabase;

    private string $environmentPath;

    private string $restoreStatePath;

    private string $maintenancePath;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('backups');
        $this->environmentPath = storage_path('framework/testing/admin-restore-environment-'.bin2hex(random_bytes(8)));
        file_put_contents($this->environmentPath, "APP_KEY=base64:admin-restore-key\n");
        config()->set('waymark.backups.environment_path', $this->environmentPath);
        config()->set('waymark.backups.restore_environment_path', $this->environmentPath);
        config()->set('waymark.backups.destination_disk', 'backups');
        $this->restoreStatePath = $this->environmentPath.'.restore-state.json';
        $this->maintenancePath = $this->environmentPath.'.maintenance.json';
        config()->set('waymark.backups.restore_state_path', $this->restoreStatePath);
        config()->set('waymark.maintenance.state_path', $this->maintenancePath);
    }

    protected function tearDown(): void
    {
        @unlink($this->environmentPath);
        @unlink($this->restoreStatePath);
        @unlink($this->maintenancePath);
        parent::tearDown();
    }

    public function test_restore_page_requires_administrator_and_recent_sensitive_assurance(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $this->actingAs($administrator)->get('/admin/backup-restore')->assertRedirect(route('password.confirm'));

        $this->withSession($this->assuredSession($administrator))
            ->get('/admin/backup-restore')
            ->assertSuccessful()
            ->assertSee('Restore a backup')
            ->assertSee('RESTORE WAYMARK');

        $this->actingAs(User::factory()->create())->withSession($this->assuredSession($administrator))
            ->get('/admin/backup-restore')->assertForbidden();
    }

    public function test_administrator_restore_waits_for_a_bounded_verified_safety_backup(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $profile = SiteProfile::query()->create(['group_name' => 'Before Damage']);
        $backup = app(CreateBackup::class)->handle('manual');
        $profile->update(['group_name' => 'After Damage']);

        $this->actingAs($administrator);
        session()->put($this->assuredSession($administrator));

        Livewire::test(BackupRestore::class)
            ->set('backupId', $backup->id)
            ->set('restoreConfirmation', 'RESTORE WAYMARK')
            ->call('restore');

        $this->assertSame('After Damage', SiteProfile::query()->sole()->group_name);
        $safety = BackupRun::query()->where('trigger', 'pre-restore')->sole();
        $this->assertSame('queued', $safety->status);

        Livewire::test(BackupRestore::class)->call('continueRestore');
        $this->assertSame('After Damage', SiteProfile::query()->sole()->group_name);
        $this->assertContains($safety->fresh()->status, ['queued', 'running']);

        while (in_array($safety->fresh()->status, ['queued', 'running'], true)) {
            app(CreateBackup::class)->advance($safety->fresh(), 500);
        }

        Livewire::test(BackupRestore::class)
            ->call('continueRestore')
            ->assertRedirect('/admin/system-health');

        $this->assertSame('Before Damage', SiteProfile::query()->sole()->group_name);
    }

    public function test_fallback_advances_restore_safety_backup_without_starting_restore(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $profile = SiteProfile::query()->create(['group_name' => 'Fallback Target']);
        $target = app(CreateBackup::class)->handle('manual');
        $profile->update(['group_name' => 'Fallback Current']);
        $this->actingAs($administrator);
        session()->put($this->assuredSession($administrator));

        Livewire::test(BackupRestore::class)
            ->set('backupId', $target->id)
            ->set('restoreConfirmation', 'RESTORE WAYMARK')
            ->call('restore');
        $safety = BackupRun::query()->where('trigger', 'pre-restore')->sole();

        $handled = app(WaymarkFallbackWorkload::class)->run();

        $this->assertSame(1, $handled['backup_steps']);
        $this->assertSame('copy_private', $safety->fresh()->stage);
        $this->assertSame('Fallback Current', SiteProfile::query()->sole()->group_name);
    }

    public function test_failed_restore_safety_backup_leaves_target_untouched_and_retryable(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $profile = SiteProfile::query()->create(['group_name' => 'Failure Target']);
        $target = app(CreateBackup::class)->handle('manual');
        $profile->update(['group_name' => 'Failure Current']);
        config()->set('waymark.backups.environment_path', $this->environmentPath.'.missing');
        $this->actingAs($administrator);
        session()->put($this->assuredSession($administrator));

        Livewire::test(BackupRestore::class)
            ->set('backupId', $target->id)
            ->set('restoreConfirmation', 'RESTORE WAYMARK')
            ->call('restore')
            ->call('continueRestore');

        $safety = BackupRun::query()->where('trigger', 'pre-restore')->sole();
        $this->assertSame('failed', $safety->status);
        $this->assertTrue($safety->retryable);
        $this->assertSame('Failure Current', SiteProfile::query()->sole()->group_name);
    }

    /** @return array<string, int> */
    private function assuredSession(User $user): array
    {
        return [
            'auth.password_confirmed_at' => now()->unix(),
            SensitiveActionAssurance::PASSWORD_CONFIRMED_USER_ID => $user->id,
        ];
    }
}
