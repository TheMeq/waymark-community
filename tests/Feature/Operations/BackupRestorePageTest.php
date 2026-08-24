<?php

namespace Tests\Feature\Operations;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Security\SensitiveActionAssurance;
use App\Domain\Operations\Backups\Actions\CreateBackup;
use App\Domain\Operations\Models\SiteProfile;
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
    }

    protected function tearDown(): void
    {
        @unlink($this->environmentPath);
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

    public function test_administrator_can_restore_a_known_verified_backup_with_exact_confirmation(): void
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
            ->call('restore')
            ->assertRedirect('/admin/system-health');

        $this->assertSame('Before Damage', SiteProfile::query()->sole()->group_name);
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
