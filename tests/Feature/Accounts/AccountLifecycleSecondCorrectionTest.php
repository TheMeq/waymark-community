<?php

namespace Tests\Feature\Accounts;

use App\Domain\Accounts\Actions\ApproveAccountDeletion;
use App\Domain\Accounts\Actions\ProcessPersonalDataExports;
use App\Domain\Accounts\Actions\RequestAccountDeletion;
use App\Domain\Accounts\Actions\UpdateAccountProfile;
use App\Domain\Accounts\Data\AccountProfileData;
use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Models\PersonalDataExport;
use App\Domain\Membership\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

final class AccountLifecycleSecondCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletion_invalidates_an_existing_session_and_prevents_profile_repopulation(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $account = User::factory()->create(['name' => 'Private Person', 'phone' => '07000 000000']);
        $request = app(RequestAccountDeletion::class)->handle($account);
        app(ApproveAccountDeletion::class)->handle($administrator, $request);

        $this->actingAs($account->fresh())
            ->patch('/account/profile', ['name' => 'Restored Identity', 'phone' => '07000 111111'])
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(AccountStatus::Disabled, $account->fresh()->account_status);
        $this->assertSame('Deleted member', $account->fresh()->name);
        $this->assertNull($account->fresh()->phone);

        $this->expectException(ValidationException::class);
        app(UpdateAccountProfile::class)->handle($account->fresh(), new AccountProfileData('Restored Identity', null, null, []));
    }

    public function test_pending_cleanup_keeps_a_safe_path_after_delete_failure_then_clears_it_only_after_a_later_success(): void
    {
        $account = User::factory()->create();
        $path = 'account-exports/'.$account->id.'/'.Str::uuid().'.json';
        $export = $this->export($account, 'ready', now()->subMinute(), $path);
        $disk = Mockery::mock();
        $disk->shouldReceive('delete')->with($path)->once()->andReturnFalse();
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        app(ProcessPersonalDataExports::class)->handle();

        $export->refresh();
        $this->assertSame('expired', $export->status);
        $this->assertSame($path, $export->storage_path);
        $this->assertNull($export->download_token_hash);

        $this->app->forgetInstance('filesystem');
        Storage::clearResolvedInstance('filesystem');
        Storage::fake('local');
        Storage::disk('local')->put($path, '{}');
        app(ProcessPersonalDataExports::class)->handle();

        $this->assertNull($export->fresh()->storage_path);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_stale_claim_recovery_is_bounded_by_the_processor_limit(): void
    {
        $exports = collect(range(1, 3))->map(fn () => $this->export(User::factory()->create(), 'processing'));
        $exports->each(fn (PersonalDataExport $export) => $export->update(['processing_started_at' => now()->subMinutes(16)]));

        $processed = app(ProcessPersonalDataExports::class)->handle(2);

        $this->assertSame(2, $processed);
        $this->assertSame(2, PersonalDataExport::query()->where('status', 'failed')->count());
        $this->assertSame(1, PersonalDataExport::query()->where('status', 'processing')->count());
    }

    public function test_export_lifecycle_actions_consistently_lock_the_account_before_its_exports(): void
    {
        $source = file_get_contents(app_path('Domain/Accounts/Actions/ProcessPersonalDataExports.php'));

        foreach (['claim', 'finalise', 'failOrRevoke', 'revokeIfProcessing'] as $method) {
            preg_match('/private function '.$method.'\b.*?(?=\n    private function|\n    \}\n\})/s', (string) $source, $matches);
            $body = $matches[0] ?? '';

            $this->assertNotSame('', $body);
            $this->assertLessThan(
                strpos($body, 'PersonalDataExport::query()->lockForUpdate'),
                strpos($body, 'User::query()->lockForUpdate'),
            );
        }
    }

    private function export(User $user, string $status, ?\DateTimeInterface $expiresAt = null, ?string $path = null): PersonalDataExport
    {
        $export = PersonalDataExport::query()->create([
            'user_id' => $user->id,
            'status' => $status,
            'storage_path' => $path ?? 'account-exports/'.$user->id.'/'.Str::uuid().'.json',
            'requested_at' => now(),
            'processing_started_at' => $status === 'processing' ? now() : null,
            'ready_at' => $status === 'ready' ? now() : null,
            'expires_at' => $expiresAt,
        ]);
        $export->setDownloadToken('token-'.$export->id);

        return $export->fresh();
    }
}
