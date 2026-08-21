<?php

namespace Tests\Feature\Accounts;

use App\Domain\Accounts\Actions\RecordMembershipVerification;
use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Accounts\Models\RoleCapability;
use App\Domain\Membership\Enums\MembershipStatus;
use App\Domain\Membership\Queries\MembershipReviewsDue;
use App\Filament\Pages\MembershipVerification;
use App\Filament\Widgets\MembershipReviewsDueWidget;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class MembershipVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_accounts_with_the_membership_verification_capability_can_record_a_verification(): void
    {
        $account = User::factory()->create();
        $action = app(RecordMembershipVerification::class);

        foreach ([
            AccountRole::RegisteredUser,
            AccountRole::WalkLeader,
            AccountRole::Moderator,
        ] as $role) {
            $actor = User::factory()->create(['role' => $role]);

            try {
                $action->handle($actor, $account, MembershipStatus::Verified, 'Membership card', '2026-10-01');
                $this->fail("{$role->value} unexpectedly recorded membership verification.");
            } catch (AuthorizationException) {
                $this->assertSame(MembershipStatus::Unverified, $account->fresh()->membership_status);
            }
        }

        $configuredModerator = User::factory()->create(['role' => AccountRole::Moderator]);
        RoleCapability::query()->create([
            'role' => AccountRole::Moderator,
            'capability' => ModuleCapability::ManageMembershipVerification,
        ]);

        $action->handle($configuredModerator, $account, MembershipStatus::Verified, 'Membership card', '2026-10-01');

        $this->assertSame(MembershipStatus::Verified, $account->fresh()->membership_status);
    }

    public function test_recording_verification_uses_the_authenticated_verifier_and_current_timestamp_without_changing_role(): void
    {
        Carbon::setTestNow('2026-08-21 12:34:56');

        try {
            $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
            $account = User::factory()->create([
                'role' => AccountRole::RegisteredUser,
                'membership_status' => MembershipStatus::Unverified,
            ]);

            app(RecordMembershipVerification::class)->handle(
                $administrator,
                $account,
                MembershipStatus::Verified,
                'Membership card checked in person',
                '2026-11-15',
            );

            $account->refresh();

            $this->assertSame(MembershipStatus::Verified, $account->membership_status);
            $this->assertSame($administrator->id, $account->membership_verified_by_user_id);
            $this->assertTrue(Carbon::parse('2026-08-21 12:34:56')->equalTo($account->membership_verified_at));
            $this->assertSame('Membership card checked in person', $account->membership_verification_source);
            $this->assertSame('2026-11-15', $account->membership_review_due_at->toDateString());
            $this->assertSame(AccountRole::RegisteredUser, $account->role);
            $this->assertFalse($account->hasCapability(ModuleCapability::ManageMembershipVerification));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_verification_updates_replace_metadata_and_reject_invalid_input_before_persisting(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $originalVerifier = User::factory()->create();
        $account = User::factory()->create([
            'membership_status' => MembershipStatus::Verified,
            'membership_verified_by_user_id' => $originalVerifier->id,
            'membership_verified_at' => '2026-08-01 09:00:00',
            'membership_verification_source' => 'Old source',
            'membership_review_due_at' => '2026-09-01',
        ]);
        $action = app(RecordMembershipVerification::class);

        try {
            $action->handle($administrator, $account, 'unknown', 'New source', '2026-10-01');
            $this->fail('An unsupported verification status was accepted.');
        } catch (ValidationException) {
            $account->refresh();
            $this->assertSame(MembershipStatus::Verified, $account->membership_status);
            $this->assertSame('Old source', $account->membership_verification_source);
        }

        try {
            $action->handle($administrator, $account, MembershipStatus::Lapsed, str_repeat('x', 256), 'not-a-date');
            $this->fail('Invalid verification metadata was accepted.');
        } catch (ValidationException) {
            $account->refresh();
            $this->assertSame(MembershipStatus::Verified, $account->membership_status);
            $this->assertSame('Old source', $account->membership_verification_source);
        }

        $action->handle($administrator, $account, MembershipStatus::Lapsed, null, null);

        $account->refresh();
        $this->assertSame(MembershipStatus::Lapsed, $account->membership_status);
        $this->assertSame($administrator->id, $account->membership_verified_by_user_id);
        $this->assertNull($account->membership_verification_source);
        $this->assertNull($account->membership_review_due_at);
    }

    public function test_verification_page_is_capability_guarded_and_records_the_selected_account(): void
    {
        $account = User::factory()->create();
        $moderator = User::factory()->create(['role' => AccountRole::Moderator]);
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);

        $this->actingAs($moderator)
            ->get('/admin/membership-verification')
            ->assertForbidden();

        $this->actingAs($administrator);

        Livewire::test(MembershipVerification::class)
            ->fillForm([
                'account_id' => $account->id,
                'status' => MembershipStatus::NotMember->value,
                'source' => 'Administrator review',
                'review_due_at' => '2026-09-30',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'id' => $account->id,
            'membership_status' => MembershipStatus::NotMember->value,
            'membership_verified_by_user_id' => $administrator->id,
            'membership_verification_source' => 'Administrator review',
            'membership_review_due_at' => '2026-09-30',
        ]);
    }

    public function test_selecting_an_account_loads_its_current_verification_record_for_review_or_update(): void
    {
        $account = User::factory()->create([
            'membership_status' => MembershipStatus::Verified,
            'membership_verification_source' => 'Original review',
            'membership_review_due_at' => '2026-10-20',
        ]);
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);

        $this->actingAs($administrator);

        Livewire::test(MembershipVerification::class)
            ->set('data.account_id', $account->id)
            ->assertSet('data.status', MembershipStatus::Verified->value)
            ->assertSet('data.source', 'Original review')
            ->assertSet('data.review_due_at', '2026-10-20');
    }

    public function test_dashboard_lists_only_reviews_due_on_or_before_the_installation_local_day_without_expiring_status(): void
    {
        config()->set('app.timezone', 'Europe/London');
        Carbon::setTestNow(Carbon::parse('2026-08-21 00:30:00', 'Europe/London'));

        try {
            $dueYesterday = User::factory()->create([
                'name' => 'Due yesterday',
                'membership_status' => MembershipStatus::Verified,
                'membership_review_due_at' => '2026-08-20',
            ]);
            $dueToday = User::factory()->create([
                'name' => 'Due today',
                'membership_status' => MembershipStatus::Lapsed,
                'membership_review_due_at' => '2026-08-21',
            ]);
            $future = User::factory()->create([
                'name' => 'Future review',
                'membership_status' => MembershipStatus::Verified,
            ]);

            DB::table('users')->where('id', $dueYesterday->id)->update(['membership_review_due_at' => '2026-08-20']);
            DB::table('users')->where('id', $dueToday->id)->update(['membership_review_due_at' => '2026-08-21']);
            DB::table('users')->where('id', $future->id)->update(['membership_review_due_at' => '2026-08-22']);

            $reviews = app(MembershipReviewsDue::class)->handle();

            $this->assertSame([$dueYesterday->id, $dueToday->id], $reviews->pluck('id')->all());
            $this->assertSame(MembershipStatus::Verified, $dueYesterday->fresh()->membership_status);
            $this->assertSame(MembershipStatus::Lapsed, $dueToday->fresh()->membership_status);
            $this->assertFalse($reviews->contains('id', $future->id));

            $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
            $this->actingAs($administrator);

            Livewire::test(MembershipReviewsDueWidget::class)
                ->assertSee('Membership reviews due')
                ->assertSee('Due yesterday')
                ->assertSee('Due today')
                ->assertDontSee('Future review');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_accounts_have_no_public_membership_verification_request_surface_and_profile_hides_verification_metadata(): void
    {
        $account = User::factory()->create([
            'membership_verification_source' => 'Private membership card reference',
        ]);

        $requestRoutes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => preg_match('/membership.*verification.*request|verification.*request.*membership/i', $route->uri()) === 1);
        $publicVerificationRoutes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'membership-verification'))
            ->reject(fn ($route): bool => str_starts_with($route->uri(), 'admin/'));

        $this->assertTrue($requestRoutes->isEmpty());
        $this->assertTrue($publicVerificationRoutes->isEmpty());
        $this->assertFalse(Route::has('account.membership-verification.request'));

        $this->actingAs($account)
            ->get('/account/profile')
            ->assertOk()
            ->assertDontSee('Private membership card reference')
            ->assertDontSee('Membership verification');
    }
}
