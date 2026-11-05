<?php

namespace App\Http\Controllers;

use App\Domain\Content\Presentation\PublicUrl;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Domain\SiteMedia\Actions\RenderInstallationIcon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class PwaController
{
    public function __construct(private readonly RenderInstallationIcon $icons) {}

    public function manifest(): JsonResponse
    {
        $profile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;
        $theme = BrandTheme::fromSiteProfile($profile);
        $name = $this->textOrFallback($profile->group_name, 'Waymark Community');
        $brandedIcons = $this->icons->available($profile);
        $iconUrl = fn (int $size): string => $brandedIcons
            ? PublicUrl::route('pwa.icon', ['size' => $size, 'logo' => $profile->logo_media_id ?? substr(hash('sha256', (string) $profile->logo_path), 0, 16)])
            : PublicUrl::asset('/images/pwa/icon-'.$size.'.png');

        return response()->json([
            'name' => $name,
            'short_name' => $this->textOrFallback($profile->short_name, 'Waymark'),
            'start_url' => route('home', absolute: false),
            'scope' => $this->scope(),
            'display' => 'standalone',
            'theme_color' => $theme->primaryColour,
            'background_color' => $theme->accentColour,
            'lang' => str_replace('_', '-', $this->textOrFallback($profile->locale, 'en')),
            'dir' => 'ltr',
            'icons' => [
                ['src' => $iconUrl(192), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $iconUrl(512), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
        ], 200, [
            'Content-Type' => 'application/manifest+json; charset=UTF-8',
            'Cache-Control' => 'no-cache, private',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function icon(int $size, RenderInstallationIcon $icons): Response
    {
        abort_unless(in_array($size, [192, 512], true), 404);
        $profile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;
        $contents = $icons->render($profile, $size) ?? file_get_contents(public_path('images/pwa/icon-'.$size.'.png'));

        return response($contents, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'no-cache, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function serviceWorker(): Response
    {
        $staticAssets = [
            PublicUrl::asset('/images/pwa/icon-192.png'),
            PublicUrl::asset('/images/pwa/icon-512.png'),
            route('pwa.offline', absolute: false),
        ];

        return response()
            ->view('pwa.service-worker', [
                'cacheVersion' => 'waymark-shell-v2',
                'staticAssets' => $staticAssets,
                'offlineUrl' => route('pwa.offline', absolute: false),
                'applicationBasePath' => rtrim($this->scope(), '/'),
                'buildPath' => parse_url(PublicUrl::asset('/build/'), PHP_URL_PATH),
            ])
            ->header('Content-Type', 'application/javascript; charset=UTF-8')
            ->header('Service-Worker-Allowed', $this->scope())
            ->header('Cache-Control', 'no-cache, private');
    }

    public function offline(): Response
    {
        $profile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;

        return response()
            ->view('pwa.offline', [
                'site' => ['name' => $this->textOrFallback($profile->group_name, 'Waymark Community')],
                'theme' => BrandTheme::fromSiteProfile($profile),
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    private function textOrFallback(mixed $value, string $fallback): string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : $fallback;
    }

    private function scope(): string
    {
        $basePath = rtrim((string) parse_url(route('home', absolute: false), PHP_URL_PATH), '/');

        return $basePath === '' ? '/' : $basePath.'/';
    }
}
