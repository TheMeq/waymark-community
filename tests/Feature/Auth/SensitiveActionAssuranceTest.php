<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Tests\TestCase;

final class SensitiveActionAssuranceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'sensitive.confirmed'])
            ->get('/_testing/sensitive-action', fn () => response('secured'))
            ->name('testing.sensitive-action');
        Route::middleware(['web', 'auth', 'sensitive.confirmed'])
            ->post('/_testing/sensitive-action', fn () => response('secured'));
    }

    public function test_sensitive_action_for_a_user_without_two_factor_requires_only_a_recent_password_confirmation(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->actingAs($user)
            ->get('/_testing/sensitive-action')
            ->assertRedirect(route('password.confirm'))
            ->assertSessionHas('sensitive.intended_destination', url('/_testing/sensitive-action'))
            ->assertSessionMissing('url.intended');

        $this->post(route('password.confirm.store'), ['password' => 'password'])
            ->assertRedirect('/_testing/sensitive-action')
            ->assertSessionHas('auth.password_confirmed_at')
            ->assertSessionHas('sensitive.password_confirmed_user_id', $user->id);

        $this->get('/_testing/sensitive-action')
            ->assertOk()
            ->assertSee('secured');
    }

    public function test_enabled_two_factor_requires_a_fresh_second_factor_after_password_confirmation(): void
    {
        $user = $this->twoFactorEnabledUser();
        $this->fakeTwoFactorProvider(validCode: '123456');

        $this->actingAs($user)
            ->get('/_testing/sensitive-action')
            ->assertRedirect(route('password.confirm'))
            ->assertSessionHas('sensitive.intended_destination', url('/_testing/sensitive-action'));

        $this->post(route('password.confirm.store'), ['password' => 'password'])
            ->assertRedirect('/_testing/sensitive-action');

        $this->get('/_testing/sensitive-action')
            ->assertRedirect(route('account.sensitive-confirmation.create'))
            ->assertSessionHas('sensitive.intended_destination', url('/_testing/sensitive-action'));

        $this->post(route('account.sensitive-confirmation.store'), ['code' => '123456'])
            ->assertRedirect('/_testing/sensitive-action')
            ->assertSessionHas('sensitive.two_factor_confirmed_at')
            ->assertSessionHas('sensitive.two_factor_confirmed_user_id', $user->id);

        $this->get('/_testing/sensitive-action')
            ->assertOk()
            ->assertSee('secured');
    }

    public function test_an_unsafe_sensitive_request_never_uses_an_external_referer_as_its_password_destination(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->actingAs($user)
            ->withHeader('referer', 'https://attacker.example/password-stage')
            ->post('/_testing/sensitive-action')
            ->assertRedirect(route('password.confirm'))
            ->assertSessionMissing('sensitive.intended_destination')
            ->assertSessionMissing('url.intended');

        $this->post(route('password.confirm.store'), ['password' => 'password'])
            ->assertRedirect(route('new-here'))
            ->assertSessionMissing('sensitive.intended_destination')
            ->assertSessionMissing('url.intended');
    }

    public function test_an_unsafe_sensitive_request_never_uses_an_external_referer_as_its_second_factor_destination(): void
    {
        $user = $this->twoFactorEnabledUser();
        $this->fakeTwoFactorProvider(validCode: '123456');

        $this->actingAs($user)
            ->withSession([
                'auth.password_confirmed_at' => now()->unix(),
                'sensitive.password_confirmed_user_id' => $user->id,
            ])
            ->withHeader('referer', 'https://attacker.example/second-factor-stage')
            ->post('/_testing/sensitive-action')
            ->assertRedirect(route('account.sensitive-confirmation.create'))
            ->assertSessionMissing('sensitive.intended_destination')
            ->assertSessionMissing('url.intended');

        $this->post(route('account.sensitive-confirmation.store'), ['code' => '123456'])
            ->assertRedirect(route('home'))
            ->assertSessionMissing('sensitive.intended_destination')
            ->assertSessionMissing('url.intended');
    }

    public function test_a_malicious_preseeded_intended_destination_is_discarded_before_password_confirmation_redirects(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->actingAs($user)
            ->withSession([
                'sensitive.intended_destination' => 'https://attacker.example/preseeded-sensitive',
                'url.intended' => 'https://attacker.example/preseeded-framework',
            ])
            ->post(route('password.confirm.store'), ['password' => 'password'])
            ->assertRedirect(route('new-here'))
            ->assertSessionMissing('sensitive.intended_destination')
            ->assertSessionMissing('url.intended');
    }

    public function test_the_sensitive_confirmation_route_is_not_saved_as_its_own_password_destination(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->actingAs($user)
            ->get(route('account.sensitive-confirmation.create'))
            ->assertRedirect(route('password.confirm'))
            ->assertSessionMissing('sensitive.intended_destination')
            ->assertSessionMissing('url.intended');

        $this->post(route('password.confirm.store'), ['password' => 'password'])
            ->assertRedirect(route('new-here'));
    }

    public function test_an_invalid_sensitive_second_factor_code_does_not_mark_the_session_fresh(): void
    {
        $user = $this->twoFactorEnabledUser();
        $this->fakeTwoFactorProvider(validCode: '123456');

        $this->actingAs($user)
            ->withSession([
                'auth.password_confirmed_at' => now()->unix(),
                'sensitive.password_confirmed_user_id' => $user->id,
            ])
            ->from(route('account.sensitive-confirmation.create'))
            ->post(route('account.sensitive-confirmation.store'), ['code' => '000000'])
            ->assertRedirect(route('account.sensitive-confirmation.create'))
            ->assertSessionHasErrors('code')
            ->assertSessionMissing('sensitive.two_factor_confirmed_at');
    }

    public function test_expired_sensitive_assurance_requires_a_new_challenge(): void
    {
        $user = $this->twoFactorEnabledUser();

        try {
            Carbon::setTestNow('2026-08-21 12:00:00');

            $this->actingAs($user)
                ->withSession([
                    'auth.password_confirmed_at' => now()->unix(),
                    'sensitive.password_confirmed_user_id' => $user->id,
                    'sensitive.two_factor_confirmed_at' => now()->unix(),
                    'sensitive.two_factor_confirmed_user_id' => $user->id,
                ])
                ->get('/_testing/sensitive-action')
                ->assertOk();

            Carbon::setTestNow('2026-08-21 12:15:01');

            $this->get('/_testing/sensitive-action')
                ->assertRedirect(route('password.confirm'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_an_unconfirmed_two_factor_setup_does_not_require_a_second_factor_for_sensitive_actions(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $user->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt('unconfirmed-secret'),
        ])->save();

        $this->actingAs($user)
            ->withSession([
                'auth.password_confirmed_at' => now()->unix(),
                'sensitive.password_confirmed_user_id' => $user->id,
            ])
            ->get('/_testing/sensitive-action')
            ->assertOk()
            ->assertSee('secured');
    }

    public function test_sensitive_assurance_cannot_be_reused_by_a_different_authenticated_user(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $this->actingAs($firstUser)
            ->withSession([
                'auth.password_confirmed_at' => now()->unix(),
                'sensitive.password_confirmed_user_id' => $firstUser->id,
            ])
            ->get('/_testing/sensitive-action')
            ->assertOk();

        $this->actingAs($secondUser)
            ->get('/_testing/sensitive-action')
            ->assertRedirect(route('password.confirm'));
    }

    public function test_account_security_page_requires_authentication_and_recent_password_confirmation(): void
    {
        $this->get(route('account.security.show'))->assertRedirect(route('login'));

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('account.security.show'))
            ->assertRedirect(route('password.confirm'));

        $this->withSession([
            'auth.password_confirmed_at' => now()->unix(),
            'sensitive.password_confirmed_user_id' => $user->id,
        ])
            ->get(route('account.security.show'))
            ->assertOk()
            ->assertSee('Account security')
            ->assertSee('Two-factor authentication');
    }

    public function test_fortify_two_factor_enable_confirm_regenerate_and_disable_keep_secrets_encrypted(): void
    {
        $user = User::factory()->create();
        $this->fakeTwoFactorProvider(validCode: '123456');

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => now()->unix()])
            ->from(route('account.security.show'))
            ->post(route('two-factor.enable'))
            ->assertRedirect(route('account.security.show'));

        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNotSame('fortify-test-secret', $user->two_factor_secret);
        $this->assertNotNull($user->two_factor_recovery_codes);
        $this->assertStringNotContainsString('recovery-one', $user->two_factor_recovery_codes);
        $this->assertNull($user->two_factor_confirmed_at);

        $this->from(route('account.security.show'))
            ->post(route('two-factor.confirm'), ['code' => '123456'])
            ->assertRedirect(route('account.security.show'));

        $user->refresh();
        $this->assertTrue($user->hasEnabledTwoFactorAuthentication());
        $originalRecoveryCodes = $user->recoveryCodes();
        $this->assertCount(8, $originalRecoveryCodes);

        $this->from(route('account.security.show'))
            ->post(route('two-factor.regenerate-recovery-codes'))
            ->assertRedirect(route('account.security.show'));

        $user->refresh();
        $this->assertCount(8, $user->recoveryCodes());
        $this->assertNotSame($originalRecoveryCodes, $user->recoveryCodes());

        $this->from(route('account.security.show'))
            ->delete(route('two-factor.disable'))
            ->assertRedirect(route('account.security.show'));

        $user->refresh();
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_disabling_two_factor_clears_the_current_second_factor_assurance(): void
    {
        $user = $this->twoFactorEnabledUser();

        $this->actingAs($user)
            ->withSession([
                'auth.password_confirmed_at' => now()->unix(),
                'sensitive.password_confirmed_user_id' => $user->id,
                'sensitive.two_factor_confirmed_at' => now()->unix(),
                'sensitive.two_factor_confirmed_user_id' => $user->id,
            ])
            ->from(route('account.security.show'))
            ->delete(route('two-factor.disable'))
            ->assertRedirect(route('account.security.show'))
            ->assertSessionMissing('sensitive.two_factor_confirmed_at')
            ->assertSessionMissing('sensitive.two_factor_confirmed_user_id');
    }

    public function test_changing_a_password_clears_sensitive_assurance(): void
    {
        $user = $this->twoFactorEnabledUser();

        $this->actingAs($user)
            ->withSession([
                'auth.password_confirmed_at' => now()->unix(),
                'sensitive.password_confirmed_user_id' => $user->id,
                'sensitive.two_factor_confirmed_at' => now()->unix(),
                'sensitive.two_factor_confirmed_user_id' => $user->id,
            ])
            ->from(route('account.security.show'))
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'a-new-password',
                'password_confirmation' => 'a-new-password',
            ])
            ->assertRedirect(route('account.security.show'))
            ->assertSessionMissing('auth.password_confirmed_at')
            ->assertSessionMissing('sensitive.password_confirmed_user_id')
            ->assertSessionMissing('sensitive.two_factor_confirmed_at')
            ->assertSessionMissing('sensitive.two_factor_confirmed_user_id');

        $this->assertTrue(Hash::check('a-new-password', $user->fresh()->password));
    }

    public function test_login_still_requires_fortify_two_factor_for_confirmed_setups(): void
    {
        $user = $this->twoFactorEnabledUser();
        $this->fakeTwoFactorProvider(validCode: '123456');

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.login'));

        $this->post(route('two-factor.login.store'), ['code' => '123456'])
            ->assertRedirect('/new-here');

        $this->assertAuthenticatedAs($user);
    }

    public function test_standard_password_reset_remains_available_without_sensitive_assurance(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertGuest();
    }

    private function twoFactorEnabledUser(): User
    {
        $user = User::factory()->create(['password' => 'password']);
        $user->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt('fortify-test-secret'),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['recovery-one'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user;
    }

    private function fakeTwoFactorProvider(string $validCode): void
    {
        app()->instance(TwoFactorAuthenticationProvider::class, new class($validCode) implements TwoFactorAuthenticationProvider
        {
            public function __construct(private readonly string $validCode) {}

            public function generateSecretKey(): string
            {
                return 'fortify-test-secret';
            }

            public function qrCodeUrl($companyName, $companyEmail, $secret): string
            {
                return 'otpauth://totp/Waymark:test?secret='.$secret;
            }

            public function verify($secret, $code): bool
            {
                return $secret === 'fortify-test-secret' && hash_equals($this->validCode, (string) $code);
            }
        });
    }
}
