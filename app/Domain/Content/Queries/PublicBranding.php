<?php

namespace App\Domain\Content\Queries;

use App\Domain\Content\Presentation\PublicUrl;
use App\Domain\Content\Support\PublicContentCache;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Queries\CurrentSiteProfile;
use Illuminate\Support\Facades\Cache;

final class PublicBranding
{
    public function __construct(private readonly CurrentSiteProfile $profile) {}

    /** @return array<string, mixed> */
    public function get(): array
    {
        return Cache::remember(PublicContentCache::BRANDING, (int) config('waymark.public_cache_seconds', 300), function (): array {
            return $this->forProfile($this->profile->get());
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
            ->map(fn ($url, $label): ?array => PublicUrl::resolve($url) === null ? null : ['label' => mb_substr(trim((string) $label), 0, 60), 'url' => PublicUrl::resolve($url)])
            ->filter(fn (?array $link): bool => $link !== null && $link['label'] !== '')
            ->values()
            ->all();

        return [
            'name' => $profile->group_name ?: 'Waymark Community',
            'short_name' => $profile->short_name ?: 'W',
            'logo_url' => PublicUrl::asset($profile->logo_path),
            'favicon_url' => PublicUrl::asset($profile->favicon_path),
            'hero_url' => PublicUrl::asset($profile->hero_default_path),
            'social_links' => $socialLinks,
            'terminology' => $terminology,
            'affiliation_name' => trim((string) $profile->affiliation_name),
            'affiliation_url' => PublicUrl::resolve($profile->affiliation_url),
        ];
    }
}
