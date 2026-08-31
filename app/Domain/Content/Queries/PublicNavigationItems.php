<?php

namespace App\Domain\Content\Queries;

use App\Domain\Content\Models\NavigationItem;
use App\Domain\Content\Presentation\PublicUrl;
use App\Domain\Content\Support\PublicContentCache;
use App\Domain\Operations\Queries\CurrentSiteProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

final class PublicNavigationItems
{
    public function __construct(private readonly CurrentSiteProfile $profile) {}

    public function get(): Collection
    {
        if (! Schema::hasTable('navigation_items')) {
            return collect();
        }

        $attributes = Cache::remember(PublicContentCache::NAVIGATION, (int) config('waymark.public_cache_seconds', 300), function (): array {
            $modules = (array) $this->profile->get()->module_configuration;

            return NavigationItem::query()->where('enabled', true)->orderBy('sort_order')->orderBy('id')->get()
                ->filter(fn (NavigationItem $item): bool => $item->module_key === null || ($modules[$item->module_key] ?? true) === true)
                ->map(fn (NavigationItem $item): array => $item->getAttributes())
                ->values()
                ->all();
        });

        return NavigationItem::hydrate($attributes)->each(
            fn (NavigationItem $item): NavigationItem => $item->setAttribute('url', PublicUrl::resolve($item->url)),
        );
    }
}
