<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_accounts_have_no_attendance_tracking_surface(): void
    {
        $attendanceRoutes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'attendance'));

        $this->assertTrue($attendanceRoutes->isEmpty());
        $this->assertFalse(Schema::hasTable('attendances'));
        $this->assertFalse(Schema::hasTable('attendance_records'));
    }
}
