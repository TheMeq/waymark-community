<?php

namespace Tests\Feature\Pwa;

use App\Domain\SiteMedia\Actions\DownloadPublicIconImage;
use App\Domain\SiteMedia\Actions\ResolvePublicImageHost;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class PublicIconDownloadTest extends TestCase
{
    public function test_private_hosts_and_insecure_urls_are_not_requested(): void
    {
        Http::fake();
        $download = app(DownloadPublicIconImage::class);
        foreach (['https://127.0.0.1/logo.png', 'https://192.168.0.115/logo.png', 'http://images.example.test/logo.png'] as $url) {
            $this->assertNull($download->get($url));
        }
        Http::assertNothingSent();
    }

    public function test_external_download_pins_the_public_address_and_does_not_follow_redirects(): void
    {
        $this->app->instance(ResolvePublicImageHost::class, new class extends ResolvePublicImageHost
        {
            public function resolve(string $host): ?string
            {
                return '93.184.216.34';
            }
        });
        $seen = [];
        Http::fake(function ($request, $options) use (&$seen) {
            $seen = $options;

            return Http::response('', 302, ['Location' => 'https://127.0.0.1/private']);
        });
        $this->assertNull(app(DownloadPublicIconImage::class)->get('https://images.example.test/redirect.png'));
        $this->assertFalse($seen['allow_redirects']);
        $this->assertSame(['images.example.test:443:93.184.216.34'], $seen['curl'][CURLOPT_RESOLVE]);
        Http::assertSentCount(1);
    }
}
