<?php

namespace Tests\Feature\Accounts;

use App\Domain\Accounts\Actions\ApproveAccountDeletion;
use App\Domain\Accounts\Actions\CleanUpPersonalDataExports;
use App\Domain\Accounts\Actions\ProcessPersonalDataExports;
use App\Domain\Accounts\Actions\RequestAccountDeletion;
use App\Domain\Accounts\Actions\RequestPersonalDataExport;
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
        $disk->shouldReceive('exists')->with($path)->once()->andReturnTrue();
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

    public function test_a_reserved_path_from_a_crash_after_write_is_cleaned_before_the_export_can_retry(): void
    {
        Storage::fake('local');
        $account = User::factory()->create();
        $path = 'account-exports/'.$account->id.'/'.Str::uuid().'.json';
        $export = $this->export($account, 'processing', null, $path);
        $export->update(['processing_started_at' => now()->subMinutes(16)]);
        Storage::disk('local')->put($path, '{"personal":"data"}');

        app(ProcessPersonalDataExports::class)->handle(1);

        $export->refresh();
        $this->assertSame('failed', $export->status);
        $this->assertNull($export->storage_path);
        Storage::disk('local')->assertMissing($path);

        app(ProcessPersonalDataExports::class)->handle(1);

        $this->assertSame('ready', $export->fresh()->status);
        $this->assertNotSame($path, $export->fresh()->storage_path);
    }

    public function test_a_terminal_missing_reserved_file_is_cleared_after_the_writer_lease_has_ended(): void
    {
        Storage::fake('local');
        $account = User::factory()->create();
        $path = 'account-exports/'.$account->id.'/'.Str::uuid().'.json';
        $export = $this->export($account, 'revoked', null, $path);

        app(CleanUpPersonalDataExports::class)->handle();

        $this->assertNull($export->fresh()->storage_path);
    }

    public function test_a_write_failure_sees_the_committed_reservation_then_fails_boundedly_and_retries_with_a_new_path(): void
    {
        $account = User::factory()->create();
        $export = app(RequestPersonalDataExport::class)->handle($account);
        $disk = Mockery::mock();
        $disk->shouldReceive('exists')->once()->andReturnFalse();
        $observed = [];
        $disk->shouldReceive('put')->once()->andReturnUsing(function (...$arguments) use ($export, &$observed): never {
            $path = $arguments[0];
            $reserved = $export->fresh();
            $observed = [$reserved->status, $reserved->storage_path, $reserved->hasSafeStoragePath(), $path];

            throw new \RuntimeException('Storage write failed.');
        });
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        app(ProcessPersonalDataExports::class)->handle(1);

        $this->assertSame('processing', $observed[0]);
        $this->assertSame($observed[3], $observed[1]);
        $this->assertTrue($observed[2]);
        $this->assertSame('failed', $export->fresh()->status);
        $this->assertNull($export->fresh()->storage_path);

        $this->app->forgetInstance('filesystem');
        Storage::clearResolvedInstance('filesystem');
        Storage::fake('local');
        app(ProcessPersonalDataExports::class)->handle(1);

        $this->assertSame('ready', $export->fresh()->status);
        $this->assertTrue($export->fresh()->hasSafeStoragePath());
    }

    public function test_export_lifecycle_actions_consistently_lock_the_account_before_its_exports(): void
    {
        $source = file_get_contents(app_path('Domain/Accounts/Actions/ProcessPersonalDataExports.php'));

        foreach (['claim', 'writeAndFinalise', 'failOrRevoke', 'revokeIfProcessing'] as $method) {
            preg_match('/private function '.$method.'\b.*?(?=\n    private function|\n    \}\n\})/s', (string) $source, $matches);
            $body = $matches[0] ?? '';

            $this->assertNotSame('', $body);
            $userLock = strpos($body, 'User::query()');
            $exportLock = strpos($body, 'PersonalDataExport::query()->lockForUpdate');

            $this->assertNotFalse($userLock, 'The user row must be locked.');
            $this->assertNotFalse($exportLock, 'The export row must be locked.');
            $this->assertGreaterThan($userLock, $exportLock, 'The user lock must precede the export lock.');
        }
    }

    public function test_export_writer_holds_the_user_then_export_fence_through_storage_write_and_ready_transition(): void
    {
        $source = file_get_contents(app_path('Domain/Accounts/Actions/ProcessPersonalDataExports.php'));
        preg_match('/private function writeAndFinalise\b.*?(?=\n    private function|\n    \}\n\})/s', (string) $source, $matches);
        $body = $matches[0] ?? '';
        $userLock = strpos($body, 'User::query()->with');
        $exportLock = strpos($body, 'PersonalDataExport::query()->lockForUpdate');
        $write = strpos($body, "Storage::disk('local')->put");
        $ready = strpos($body, "'status' => 'ready'");

        $this->assertGreaterThan(0, $userLock);
        $this->assertGreaterThan($userLock, $exportLock);
        $this->assertGreaterThan($exportLock, $write);
        $this->assertGreaterThan($write, $ready);
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
