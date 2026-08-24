<?php

namespace Tests\Feature\Operations;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Security\SensitiveActionAssurance;
use App\Domain\Operations\Backups\Models\BackupRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class BackupDownloadAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_download_requires_administrator_and_fresh_sensitive_assurance(): void
    {
        Storage::fake('backups');
        Storage::disk('backups')->put('private-review.zip', 'private-backup');
        $backup = BackupRun::query()->create([
            'status' => 'completed', 'trigger' => 'manual', 'storage_disk' => 'backups', 'storage_path' => 'private-review.zip',
            'sha256' => hash('sha256', 'private-backup'), 'size_bytes' => 14, 'completed_at' => now(),
        ]);

        $this->get(route('admin.backups.download', $backup))->assertRedirect(route('login'));

        $member = User::factory()->create();
        $this->actingAs($member)->withSession($this->assuredSession($member))
            ->get(route('admin.backups.download', $backup))->assertForbidden();

        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $this->actingAs($administrator)->get(route('admin.backups.download', $backup))
            ->assertRedirect(route('password.confirm'));

        $this->withSession($this->assuredSession($administrator))
            ->get(route('admin.backups.download', $backup))
            ->assertSuccessful()
            ->assertDownload('private-review.zip');
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
