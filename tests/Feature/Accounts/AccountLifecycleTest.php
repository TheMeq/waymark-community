<?php

namespace Tests\Feature\Accounts;

use App\Domain\Accounts\Actions\ApproveAccountDeletion;
use App\Domain\Accounts\Actions\EstablishInitialInstallationOwner;
use App\Domain\Accounts\Actions\ProcessPersonalDataExports;
use App\Domain\Accounts\Actions\RequestAccountDeletion;
use App\Domain\Accounts\Actions\RequestPersonalDataExport;
use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Models\CommunicationPreference;
use App\Domain\Accounts\Models\Favourite;
use App\Domain\Accounts\Models\PersonalDataExport;
use App\Domain\Events\Models\Event;
use App\Domain\Membership\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_account_can_request_only_one_active_personal_data_export_and_it_excludes_sensitive_credentials(): void
    {
        Storage::fake('local');
        $account = User::factory()->create([
            'two_factor_secret' => 'secret-value',
            'two_factor_recovery_codes' => 'recovery-value',
            'remember_token' => 'remember-value',
            'public_profile_enabled' => true,
            'public_profile_introduction' => 'I enjoy hillside walks.',
        ]);
        $account->communicationPreferences()->create(['category' => 'group_news', 'is_subscribed' => true, 'consented_at' => now()]);

        $request = app(RequestPersonalDataExport::class)->handle($account);
        $sameRequest = app(RequestPersonalDataExport::class)->handle($account);

        $this->assertSame($request->id, $sameRequest->id);
        app(ProcessPersonalDataExports::class)->handle();
        $request->refresh();

        $this->assertSame('ready', $request->status);
        $this->assertSame($request->id, app(RequestPersonalDataExport::class)->handle($account)->id);
        Storage::disk('local')->assertExists($request->storage_path);
        $contents = Storage::disk('local')->get($request->storage_path);
        $this->assertStringContainsString('group_news', $contents);
        $this->assertStringContainsString('I enjoy hillside walks.', $contents);
        $this->assertStringNotContainsString('secret-value', $contents);
        $this->assertStringNotContainsString('recovery-value', $contents);
        $this->assertStringNotContainsString('remember-value', $contents);
        $this->assertStringNotContainsString('"password"', $contents);
    }

    public function test_ready_export_download_is_limited_to_the_authenticated_owner_and_expires(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $export = app(RequestPersonalDataExport::class)->handle($owner);
        app(ProcessPersonalDataExports::class)->handle();
        $export->refresh();

        $this->actingAs($other)->get($export->downloadUrl())->assertForbidden();
        $this->actingAs($owner)->get($export->downloadUrl())->assertOk();

        $downloadUrl = $export->downloadUrl();
        $export->forceFill(['expires_at' => now()->subSecond()])->save();
        $this->actingAs($owner)->get($downloadUrl)->assertForbidden();
    }

    public function test_export_download_rejects_a_path_outside_the_private_export_directory(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        Storage::disk('local')->put('account-exports/secret.json', '{"private": true}');
        $export = PersonalDataExport::query()->create([
            'user_id' => $owner->id, 'status' => 'ready', 'storage_path' => '../account-exports/secret.json',
            'requested_at' => now(), 'ready_at' => now(), 'expires_at' => now()->addDay(),
        ]);
        $export->setDownloadToken('safe-token');

        $this->assertFalse($export->hasSafeStoragePath());
        $this->actingAs($owner)->get($export->downloadUrl())->assertForbidden();
    }

    public function test_deletion_request_is_reviewed_not_blindly_deleted_and_anonymises_private_account_data(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $account = User::factory()->create([
            'name' => 'Private Person',
            'email' => 'private@example.test',
            'phone' => '07000 000000',
            'display_name' => 'Private',
            'profile_photo_reference' => '/images/demo/lakeside-friends.png',
            'two_factor_secret' => 'secret',
            'two_factor_recovery_codes' => 'codes',
            'two_factor_confirmed_at' => now(),
        ]);
        $account->communicationPreferences()->create(['category' => 'group_news', 'is_subscribed' => true]);
        $account->favourites()->create(['event_id' => $this->event()->id]);

        $request = app(RequestAccountDeletion::class)->handle($account);
        app(ApproveAccountDeletion::class)->handle($administrator, $request);

        $account->refresh();
        $this->assertSame(AccountStatus::Disabled, $account->account_status);
        $this->assertNotSame('Private Person', $account->name);
        $this->assertNotSame('private@example.test', $account->email);
        $this->assertNull($account->phone);
        $this->assertNull($account->display_name);
        $this->assertNull($account->profile_photo_reference);
        $this->assertNull($account->two_factor_secret);
        $this->assertNull($account->two_factor_recovery_codes);
        $this->assertSame(0, CommunicationPreference::query()->where('user_id', $account->id)->count());
        $this->assertSame(0, Favourite::query()->where('user_id', $account->id)->count());
        $this->assertSame('approved', $request->fresh()->status);
    }

    public function test_installation_owner_must_transfer_responsibility_before_requesting_deletion(): void
    {
        $owner = User::factory()->create(['role' => AccountRole::Administrator]);
        app(EstablishInitialInstallationOwner::class)->handle($owner);

        $this->expectException(ValidationException::class);
        app(RequestAccountDeletion::class)->handle($owner);
    }

    public function test_stale_accounts_are_flagged_for_review_without_automatic_deactivation_or_anonymisation(): void
    {
        $stale = User::factory()->create(['name' => 'Stale Person']);
        $fresh = User::factory()->create(['name' => 'Fresh Person']);
        $this->travelTo(now()->subDays(181));
        $this->actingAs($stale)->get('/account/profile');
        $this->travelBack();
        $this->actingAs($fresh)->get('/account/profile');

        $this->artisan('accounts:flag-stale --days=180')->assertSuccessful();

        $this->assertDatabaseHas('stale_account_reviews', ['user_id' => $stale->id, 'status' => 'pending']);
        $this->assertDatabaseMissing('stale_account_reviews', ['user_id' => $fresh->id]);
        $this->assertSame(AccountStatus::Active, $stale->fresh()->account_status);
        $this->assertSame('Stale Person', $stale->fresh()->name);
    }

    public function test_privacy_routes_require_authentication_and_sensitive_assurance_for_deletion(): void
    {
        $user = User::factory()->create();

        $this->get('/account/privacy')->assertRedirect('/login');
        $this->actingAs($user)->post('/account/privacy/exports')->assertRedirect('/account/privacy');
        $this->actingAs($user)->post('/account/privacy/deletion')
            ->assertRedirect(route('password.confirm'));
    }

    private function event(): Event
    {
        return Event::factory()->create();
    }
}
