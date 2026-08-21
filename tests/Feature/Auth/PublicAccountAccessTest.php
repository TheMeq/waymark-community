<?php

namespace Tests\Feature\Auth;

use App\Domain\Accounts\Models\CommunicationPreference;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PublicAccountAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_account_backend_exposes_required_authentication_actions(): void
    {
        $this->assertTrue(Route::has('register.store'));
        $this->assertTrue(Route::has('verification.verify'));
        $this->assertTrue(Route::has('verification.send'));
        $this->assertTrue(Route::has('two-factor.login.store'));
        $this->assertTrue(Route::has('two-factor.enable'));
    }

    public function test_public_accounts_require_email_verification_capability(): void
    {
        $this->assertInstanceOf(MustVerifyEmail::class, new User);
    }

    public function test_public_account_screens_use_the_branded_public_layout(): void
    {
        $this->get('/register')->assertOk()->assertViewIs('auth.register')->assertSee('Create your account');
        $this->get('/login')->assertOk()->assertViewIs('auth.login')->assertSee('Welcome back');
        $this->get('/forgot-password')->assertOk()->assertViewIs('auth.forgot-password')->assertSee('Reset your password');
        $this->get('/reset-password/example-token?email=walker@example.test')->assertOk()->assertViewIs('auth.reset-password')->assertSee('Choose a new password');

        $challengedUser = User::factory()->create();

        $this->withSession(['login.id' => $challengedUser->id])
            ->get('/two-factor-challenge')
            ->assertOk()
            ->assertViewIs('auth.two-factor-challenge')
            ->assertSee('Enter your code');

        $this->actingAs(User::factory()->unverified()->create())
            ->get('/email/verify')
            ->assertOk()
            ->assertViewIs('auth.verify-email')
            ->assertSee('Verify your email');

        $this->actingAs($challengedUser)
            ->get('/user/confirm-password')
            ->assertOk()
            ->assertViewIs('auth.confirm-password')
            ->assertSee('Confirm your password');
    }

    public function test_registration_sends_verification_and_starts_the_onboarding_journey(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Alex Walker',
            'email' => 'alex@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect('/new-here');

        $user = User::query()->where('email', 'alex@example.test')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_unverified_accounts_can_sign_in_and_read_the_informational_new_here_guide(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'new.walker@example.test',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/new-here');

        $this->get('/new-here')
            ->assertOk()
            ->assertSee('New here?')
            ->assertSee('up to three walks')
            ->assertSee('We do not count or record your walks')
            ->assertSee('Photos need a verified email address');
    }

    public function test_onboarding_links_unverified_accounts_to_email_verification_without_prompting_verified_accounts(): void
    {
        $unverifiedUser = User::factory()->unverified()->create();
        $verifiedUser = User::factory()->create();

        $this->actingAs($unverifiedUser)
            ->get('/new-here')
            ->assertOk()
            ->assertSee('Verify your email')
            ->assertSee(route('verification.notice'), false);

        $this->actingAs($verifiedUser)
            ->get('/new-here')
            ->assertOk()
            ->assertDontSee('Verify your email');
    }

    public function test_accounts_have_no_attendance_tracking_surface(): void
    {
        $attendanceRoutes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'attendance'));

        $tables = collect(Schema::getTables())->pluck('name');
        $forbiddenTableNames = '/attendance|participation|check.?in|trial/i';
        $forbiddenCounterColumns = [
            'attendance_count',
            'attended_walk_count',
            'attended_walks',
            'check_in_count',
            'checkins',
            'trial_count',
            'trial_walk_count',
            'trial_walks',
            'walk_count',
            'walks_attended',
            'walks_completed',
            'walks_joined',
        ];

        $counterColumns = $tables
            ->flatMap(fn (string $table): array => array_map(
                fn (string $column): string => $table.'.'.$column,
                Schema::getColumnListing($table),
            ))
            ->filter(fn (string $column): bool => in_array(strtolower(explode('.', $column)[1]), $forbiddenCounterColumns, true));

        $this->assertTrue($attendanceRoutes->isEmpty());
        $this->assertTrue($tables->every(fn (string $table): bool => preg_match($forbiddenTableNames, $table) === 0));
        $this->assertTrue($counterColumns->isEmpty());
        $this->assertSame([], array_values(array_intersect((new User)->getFillable(), $forbiddenCounterColumns)));
    }

    public function test_account_profile_settings_require_authentication_and_use_the_public_layout(): void
    {
        $this->get('/account/profile')->assertRedirect('/login');

        $user = User::factory()->create([
            'name' => '  Alex   Walker  ',
            'display_name' => null,
            'profile_photo_reference' => 'profile-photo-42',
        ]);

        $this->actingAs($user)
            ->get('/account/profile')
            ->assertOk()
            ->assertSee('Profile settings')
            ->assertSee('Alex W.')
            ->assertSee('Profile photo')
            ->assertSee(route('account.security.show'), false)
            ->assertDontSee('type="file"', false);
    }

    public function test_account_profile_updates_only_the_authenticated_account_and_records_explicit_preferences(): void
    {
        $account = User::factory()->create(['name' => 'Original Account Name']);
        $otherAccount = User::factory()->create(['name' => 'Unaffected Account Name']);

        $this->actingAs($account)
            ->patch('/account/profile', [
                'name' => 'Alex Walker',
                'display_name' => 'Alex on the hills',
                'phone' => '+44 7700 900123',
                'preferences' => [
                    'group_news' => '1',
                    'photo_moderation_outcomes' => '1',
                ],
            ])
            ->assertRedirect('/account/profile');

        $this->assertDatabaseHas('users', [
            'id' => $account->id,
            'name' => 'Alex Walker',
            'display_name' => 'Alex on the hills',
            'phone' => '+44 7700 900123',
        ]);
        $this->assertDatabaseHas('communication_preferences', [
            'user_id' => $account->id,
            'category' => 'group_news',
            'is_subscribed' => true,
        ]);
        $this->assertDatabaseHas('communication_preferences', [
            'user_id' => $account->id,
            'category' => 'photo_moderation_outcomes',
            'is_subscribed' => true,
        ]);
        $this->assertDatabaseHas('communication_preferences', [
            'user_id' => $account->id,
            'category' => 'membership_communications',
            'is_subscribed' => false,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $otherAccount->id,
            'name' => 'Unaffected Account Name',
        ]);
    }

    public function test_account_profile_rejects_invalid_details(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/account/profile')
            ->patch('/account/profile', [
                'name' => '',
                'display_name' => str_repeat('a', 101),
                'phone' => str_repeat('1', 51),
            ])
            ->assertRedirect('/account/profile')
            ->assertSessionHasErrors(['name', 'display_name', 'phone']);
    }

    public function test_profile_saves_preserve_existing_consent_and_record_a_new_opt_in_after_an_opt_out(): void
    {
        $account = User::factory()->create(['name' => 'Alex Walker']);
        $originalConsent = Carbon::parse('2026-08-20 09:00:00');

        $preference = new CommunicationPreference([
            'category' => 'group_news',
            'is_subscribed' => true,
            'consented_at' => $originalConsent,
        ]);
        $preference->user()->associate($account);
        $preference->save();

        try {
            Carbon::setTestNow('2026-08-21 09:00:00');

            $this->actingAs($account)
                ->patch('/account/profile', [
                    'name' => 'Alexandra Walker',
                    'preferences' => ['group_news' => '1'],
                ])
                ->assertRedirect('/account/profile');

            $this->assertTrue($originalConsent->equalTo($this->groupNewsPreferenceFor($account)->consented_at));

            Carbon::setTestNow('2026-08-21 10:00:00');

            $this->actingAs($account)
                ->patch('/account/profile', [
                    'name' => 'Alexandra Walker',
                    'display_name' => 'Alex',
                    'preferences' => ['group_news' => '1'],
                ])
                ->assertRedirect('/account/profile');

            $this->assertTrue($originalConsent->equalTo($this->groupNewsPreferenceFor($account)->consented_at));

            $this->actingAs($account)
                ->patch('/account/profile', [
                    'name' => 'Alexandra Walker',
                ])
                ->assertRedirect('/account/profile');

            $this->assertFalse($this->groupNewsPreferenceFor($account)->is_subscribed);
            $this->assertNull($this->groupNewsPreferenceFor($account)->consented_at);

            Carbon::setTestNow('2026-08-21 11:00:00');

            $this->actingAs($account)
                ->patch('/account/profile', [
                    'name' => 'Alexandra Walker',
                    'preferences' => ['group_news' => '1'],
                ])
                ->assertRedirect('/account/profile');

            $preference = $this->groupNewsPreferenceFor($account);
            $this->assertTrue($preference->is_subscribed);
            $this->assertTrue(Carbon::parse('2026-08-21 11:00:00')->equalTo($preference->consented_at));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_public_attribution_uses_an_explicit_display_name_or_a_safe_privacy_fallback(): void
    {
        $user = new User(['name' => '  Alex   Walker  ', 'display_name' => null]);
        $singleNameUser = new User(['name' => 'Alex', 'display_name' => null]);
        $unnamedUser = new User(['name' => '   ', 'display_name' => null]);
        $displayNameUser = new User(['name' => 'Alex Walker', 'display_name' => '  Trail Alex  ']);

        $this->assertSame('Alex W.', $user->publicDisplayName());
        $this->assertSame('Alex', $singleNameUser->publicDisplayName());
        $this->assertSame('Member', $unnamedUser->publicDisplayName());
        $this->assertSame('Trail Alex', $displayNameUser->publicDisplayName());
    }

    public function test_accounts_expose_no_member_directory_or_profile_upload_route(): void
    {
        $forbiddenRoutes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => preg_match('/(?:^|\/)(?:members|directory)(?:\/|$)/i', $route->uri()) === 1);

        $this->assertTrue($forbiddenRoutes->isEmpty());
        $this->assertFalse(Route::has('members.index'));
        $this->assertFalse(Route::has('account.profile.photo.store'));
        $this->assertFalse(Schema::hasTable('member_directories'));
    }

    public function test_profile_policy_only_allows_an_account_to_update_itself(): void
    {
        $account = User::factory()->create();
        $otherAccount = User::factory()->create();

        $this->assertTrue(Gate::forUser($account)->allows('updateProfile', $account));
        $this->assertFalse(Gate::forUser($account)->allows('updateProfile', $otherAccount));
    }

    private function groupNewsPreferenceFor(User $account): CommunicationPreference
    {
        return CommunicationPreference::query()
            ->where('user_id', $account->id)
            ->where('category', 'group_news')
            ->sole();
    }
}
