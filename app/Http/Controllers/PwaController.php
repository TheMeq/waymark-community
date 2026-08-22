<?php

namespace App\Http\Controllers;

use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class PwaController
{
    public function manifest(): JsonResponse
    {
        $profile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;
        $theme = BrandTheme::fromSiteProfile($profile);
        $name = $this->textOrFallback($profile->group_name, 'Waymark Community');

        return response()->json([
            'name' => $name,
            'short_name' => $this->textOrFallback($profile->short_name, 'Waymark'),
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'theme_color' => $theme->primaryColour,
            'background_color' => $theme->accentColour,
            'lang' => str_replace('_', '-', $this->textOrFallback($profile->locale, 'en')),
            'dir' => 'ltr',
            'icons' => [
                ['src' => '/images/pwa/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/images/pwa/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
        ], 200, [
            'Content-Type' => 'application/manifest+json; charset=UTF-8',
            'Cache-Control' => 'no-cache, private',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function serviceWorker(): Response
    {
        return response()
            ->view('pwa.service-worker', ['cacheVersion' => 'waymark-shell-v2'])
            ->header('Content-Type', 'application/javascript; charset=UTF-8')
            ->header('Service-Worker-Allowed', '/')
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
}
