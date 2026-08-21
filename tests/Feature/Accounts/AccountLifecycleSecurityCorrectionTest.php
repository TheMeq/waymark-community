<?php

namespace Tests\Feature\Accounts;

use App\Domain\Accounts\Actions\ApproveAccountDeletion;
use App\Domain\Accounts\Actions\ProcessPersonalDataExports;
use App\Domain\Accounts\Actions\RequestAccountDeletion;
use App\Domain\Accounts\Actions\RequestPersonalDataExport;
use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Models\PersonalDataExport;
use App\Domain\Membership\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AccountLifecycleSecurityCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletion_revokes_all_export_states_and_only_deletes_valid_private_export_files(): void
    {
        Storage::fake('local');
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $account = User::factory()->create();
        $ready = $this->export($account, 'ready', now()->addDay());
        $requested = $this->export($account, 'requested');
        $processing = $this->export($account, 'processing');
        $unsafe = $this->export($account, 'ready', now()->addDay(), '../other-user.json');
        Storage::disk('local')->put($ready->storage_path, '{"ready":true}');
        Storage::disk('local')->put('other-user.json', '{"keep":true}');
        $readyPath = $ready->storage_path;

        $request = app(RequestAccountDeletion::class)->handle($account);
        app(ApproveAccountDeletion::class)->handle($administrator, $request);

        foreach ([$ready, $requested, $processing, $unsafe] as $export) {
            $export->refresh();
            $this->assertSame('revoked', $export->status);
            $this->assertNull($export->storage_path);
            $this->assertNull($export->download_token);
            $this->assertNull($export->download_token_hash);
        }
        Storage::disk('local')->assertMissing($readyPath);
        Storage::disk('local')->assertExists('other-user.json');
    }

    public function test_disabled_accounts_cannot_download_a_previously_ready_export(): void
    {
        Storage::fake('local');
        $account = User::factory()->create(['account_status' => AccountStatus::Disabled]);
        $export = $this->export($account, 'ready', now()->addDay());
        Storage::disk('local')->put($export->storage_path, '{}');

        $this->actingAs($account)->get($export->downloadUrl())->assertForbidden();
    }

    public function test_processor_revokes_an_export_when_the_account_is_disabled_before_a_claim_is_processed(): void
    {
        $account = User::factory()->create(['account_status' => AccountStatus::Disabled]);
        $export = $this->export($account, 'requested');

        app(ProcessPersonalDataExports::class)->handle();

        $this->assertSame('revoked', $export->fresh()->status);
        $this->assertNull($export->fresh()->download_token_hash);
    }

    public function test_processor_cannot_finalise_a_ready_export_after_the_account_is_disabled_during_generation(): void
    {
        Storage::fake('local');
        $account = User::factory()->create();
        $export = $this->export($account, 'requested');
        $retrievals = 0;

        try {
            User::retrieved(function (User $retrieved) use ($account, &$retrievals): void {
                if ($retrieved->is($account) && ++$retrievals === 3) {
                    $retrieved->forceFill(['account_status' => AccountStatus::Disabled])->saveQuietly();
                }
            });

            app(ProcessPersonalDataExports::class)->handle();

            $this->assertGreaterThanOrEqual(3, $retrievals);
            $this->assertSame('revoked', $export->fresh()->status);
            $this->assertNull($export->fresh()->download_token_hash);
            Storage::disk('local')->assertDirectoryEmpty('account-exports/'.$account->id);
        } finally {
            User::flushEventListeners();
        }
    }

    public function test_stale_processing_claims_are_retried_but_fresh_claims_and_capped_failures_are_not(): void
    {
        Storage::fake('local');
        $account = User::factory()->create();
        $stale = $this->export($account, 'processing');
        $stale->update(['processing_started_at' => now()->subMinutes(16), 'attempts' => 1]);
        $fresh = $this->export(User::factory()->create(), 'processing');
        $fresh->update(['processing_started_at' => now()->subMinutes(1), 'attempts' => 1]);
        $capped = $this->export(User::factory()->create(), 'processing');
        $capped->update(['processing_started_at' => now()->subMinutes(16), 'attempts' => 3]);

        app(ProcessPersonalDataExports::class)->handle();

        $this->assertSame('ready', $stale->fresh()->status);
        $this->assertSame('processing', $fresh->fresh()->status);
        $this->assertSame('failed', $capped->fresh()->status);
        $this->assertSame(3, $capped->fresh()->attempts);
    }

    public function test_expiry_cleanup_revokes_corrupt_rows_without_deleting_an_unrelated_private_file(): void
    {
        Storage::fake('local');
        $account = User::factory()->create();
        $export = $this->export($account, 'ready', now()->subMinute(), '../other-user.json');
        Storage::disk('local')->put('other-user.json', '{"keep":true}');

        app(ProcessPersonalDataExports::class)->handle();

        $this->assertSame('expired', $export->fresh()->status);
        $this->assertNull($export->fresh()->storage_path);
        Storage::disk('local')->assertExists('other-user.json');
    }

    public function test_repeated_requests_are_serialised_at_the_account_row_before_creating_active_records(): void
    {
        $account = User::factory()->create();

        $firstExport = app(RequestPersonalDataExport::class)->handle($account);
        $secondExport = app(RequestPersonalDataExport::class)->handle($account);
        $firstDeletion = app(RequestAccountDeletion::class)->handle($account);
        $secondDeletion = app(RequestAccountDeletion::class)->handle($account);

        $this->assertSame($firstExport->id, $secondExport->id);
        $this->assertSame($firstDeletion->id, $secondDeletion->id);
        $this->assertSame(1, PersonalDataExport::query()->where('user_id', $account->id)->count());
        $this->assertSame(1, $account->deletionRequests()->count());
    }

    private function export(User $user, string $status, ?\DateTimeInterface $expiresAt = null, ?string $path = null): PersonalDataExport
    {
        $export = PersonalDataExport::query()->create([
            'user_id' => $user->id,
            'status' => $status,
            'attempts' => 0,
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
