<?php

namespace Tests\Feature\Auth;

use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProxiedAdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_https_installation_generates_secure_livewire_endpoints_over_an_http_origin(): void
    {
        config()->set('app.url', 'https://demo.example.test');
        $this->app->getProvider(AppServiceProvider::class)->boot();
        $content = $this->get('http://demo.example.test/admin/login')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-update-uri="https:\/\/demo\.example\.test\/livewire-[^\"]+\/update"/', $content);
        $this->assertMatchesRegularExpression('/data-module-url="https:\/\/demo\.example\.test\/livewire-[^\"]+"/', $content);
        $this->assertStringNotContainsString('data-update-uri="http:', $content);
    }

    public function test_http_installation_still_supports_direct_local_login(): void
    {
        config()->set('app.url', 'http://local.example.test');
        $this->app->getProvider(AppServiceProvider::class)->boot();
        $content = $this->get('http://local.example.test/admin/login')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-update-uri="http:\/\/local\.example\.test\/livewire-[^\"]+\/update"/', $content);
    }
}
