<?php

namespace App\Domain\Content\Queries;

use App\Domain\Content\Models\NavigationItem;
use App\Domain\Content\Support\PublicContentCache;
use App\Domain\Operations\Models\SiteProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

final class PublicBranding
{
    /** @return array<string, mixed> */
    public function get(): array
    {
        return Cache::remember(PublicContentCache::BRANDING, (int) config('waymark.public_cache_seconds', 300), function (): array {
            $profile = Schema::hasTable('site_profiles')
                ? (SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile)
                : new SiteProfile;

            return $this->forProfile($profile);
        });
    }

    /** @return array<string, mixed> */
    public function forProfile(SiteProfile $profile): array
    {
        $terminology = collect((array) $profile->terminology)
            ->only(['walks', 'members', 'join', 'holidays', 'gallery'])
            ->map(fn ($label): string => mb_substr(trim((string) $label), 0, 60))
            ->filter()
            ->all();
        $socialLinks = collect((array) $profile->social_links)
            ->map(fn ($url, $label): ?array => $this->url($url) === null ? null : ['label' => mb_substr(trim((string) $label), 0, 60), 'url' => $this->url($url)])
            ->filter(fn (?array $link): bool => $link !== null && $link['label'] !== '')
            ->values()
            ->all();

        return [
            'name' => $profile->group_name ?: 'Waymark Community',
            'short_name' => $profile->short_name ?: 'W',
            'logo_url' => $this->url($profile->logo_path),
            'favicon_url' => $this->url($profile->favicon_path),
            'hero_url' => $this->url($profile->hero_default_path),
            'social_links' => $socialLinks,
            'terminology' => $terminology,
            'affiliation_name' => trim((string) $profile->affiliation_name),
            'affiliation_url' => $this->url($profile->affiliation_url),
        ];
    }

    private function url(mixed $url): ?string
    {
        return is_string($url) && NavigationItem::isAllowedUrl($url) ? $url : null;
    }
}
