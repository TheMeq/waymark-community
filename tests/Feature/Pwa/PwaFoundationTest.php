<?php

namespace Tests\Feature\Pwa;

use App\Domain\Operations\Actions\UpdateSiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PwaFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_uses_the_installation_identity_and_theme(): void
    {
        app(UpdateSiteProfile::class)->handle([
            'group_name' => 'Example & Co Walkers',
            'short_name' => 'ECW',
            'locale' => 'en_GB',
            'primary_colour' => '#123456',
            'accent_colour' => '#ABCDEF',
        ]);

        $response = $this->get('/manifest.webmanifest');

        $response->assertOk();
        $this->assertStringStartsWith('application/manifest+json', (string) $response->headers->get('Content-Type'));
        $response
            ->assertJsonPath('name', 'Example & Co Walkers')
            ->assertJsonPath('short_name', 'ECW')
            ->assertJsonPath('theme_color', '#123456')
            ->assertJsonPath('background_color', '#ABCDEF')
            ->assertJsonPath('lang', 'en-GB')
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('start_url', '/')
            ->assertJsonPath('scope', '/')
            ->assertJsonPath('icons.0.src', '/images/pwa/icon-192.png')
            ->assertJsonPath('icons.0.type', 'image/png')
            ->assertJsonPath('icons.1.src', '/images/pwa/icon-512.png')
            ->assertJsonPath('icons.1.type', 'image/png');

        foreach ([192, 512] as $size) {
            $path = public_path("images/pwa/icon-{$size}.png");
            $this->assertFileExists($path);
            $dimensions = getimagesize($path);
            $this->assertSame($size, $dimensions[0]);
            $this->assertSame($size, $dimensions[1]);
            $this->assertSame(IMAGETYPE_PNG, $dimensions[2]);
        }
    }

    public function test_manifest_has_safe_defaults_when_setup_is_incomplete(): void
    {
        $response = $this->get('/manifest.webmanifest');

        $response->assertOk()
            ->assertJsonPath('name', 'Waymark Community')
            ->assertJsonPath('short_name', 'Waymark')
            ->assertJsonPath('theme_color', '#526B3F')
            ->assertJsonPath('background_color', '#D6B269')
            ->assertJsonPath('lang', 'en')
            ->assertJsonPath('dir', 'ltr');
    }

    public function test_service_worker_and_offline_page_are_public_and_cache_aware(): void
    {
        $serviceWorker = $this->get('/service-worker.js')
            ->assertOk()
            ->assertHeader('Service-Worker-Allowed', '/')
            ->assertSee('waymark-shell-v2', false)
            ->assertSee("'/offline'", false);
        $this->assertStringStartsWith('application/javascript', (string) $serviceWorker->headers->get('Content-Type'));

        $this->get('/offline')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('data-pwa-offline', false)
            ->assertSee('<style>', false)
            ->assertSee("You're offline", false)
            ->assertSee("Try again when you're back online", false);
    }

    public function test_public_layout_discovers_manifest_and_progressively_registers_the_service_worker(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false);

        $applicationScript = (string) file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString("'serviceWorker' in navigator", $applicationScript);
        $this->assertStringContainsString("register('/service-worker.js'", $applicationScript);
        $this->assertStringNotContainsString('Notification.requestPermission', $applicationScript);
        $this->assertStringNotContainsString('sync.register', $applicationScript);
    }

    public function test_service_worker_never_handles_private_mutating_or_media_requests(): void
    {
        $serviceWorker = $this->get('/service-worker.js')->getContent();

        foreach ([
            "request.method === 'GET'",
            "url.pathname.startsWith('/admin')",
            "url.pathname.startsWith('/account')",
            "url.pathname.startsWith('/leader-hub')",
            "url.pathname.startsWith('/login')",
            "url.pathname.startsWith('/register')",
            "url.pathname.startsWith('/password')",
            "url.pathname.startsWith('/photos/upload')",
            "url.pathname.startsWith('/media/')",
            "url.pathname.includes('/image/')",
            "url.pathname.includes('/download')",
            "url.pathname.startsWith('/api/')",
            "key.startsWith('waymark-')",
        ] as $rule) {
            $this->assertStringContainsString($rule, $serviceWorker);
        }

        $this->assertStringNotContainsString('push', strtolower($serviceWorker));
        $this->assertStringNotContainsString('background sync', strtolower($serviceWorker));
    }
}
