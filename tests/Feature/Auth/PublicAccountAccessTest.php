<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class PublicAccountAccessTest extends TestCase
{
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
        $this->assertInstanceOf(MustVerifyEmail::class, new User());
    }
}
