<?php

namespace Tests\Feature\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Tests\TestCase;

final class AuthenticationPrefixNavigationTest extends TestCase
{
    protected function tearDown(): void
    {
        URL::forceRootUrl(null);

        parent::tearDown();
    }

    public function test_authentication_responses_keep_the_configured_application_prefix(): void
    {
        URL::forceRootUrl('https://example.test/demo-site/ndwg');
        URL::forceScheme('https');

        $request = Request::create('/demo-site/ndwg/login', 'POST');

        $this->assertSame(
            'https://example.test/demo-site/ndwg/new-here',
            app(LoginResponse::class)->toResponse($request)->getTargetUrl(),
        );
        $this->assertSame(
            'https://example.test/demo-site/ndwg/new-here',
            app(RegisterResponse::class)->toResponse($request)->getTargetUrl(),
        );
        $this->assertSame(
            'https://example.test/demo-site/ndwg/new-here',
            app(TwoFactorLoginResponse::class)->toResponse($request)->getTargetUrl(),
        );
        $this->assertSame(
            'https://example.test/demo-site/ndwg',
            app(LogoutResponse::class)->toResponse($request)->getTargetUrl(),
        );
    }
}
