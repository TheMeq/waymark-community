<?php

namespace App\Domain\Content\Queries;

use App\Domain\Content\Models\HomepageSection;
use App\Domain\Content\Presentation\PublicUrl;
use App\Domain\Content\Support\PublicContentCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class VisibleHomepageSections
{
    /** @return Collection<int, HomepageSection> */
    public function get(): Collection
    {
        $attributes = Cache::remember(
            PublicContentCache::HOMEPAGE_SECTIONS,
            (int) config('waymark.public_cache_seconds', 300),
            fn (): array => $this->query()->map(fn (HomepageSection $section): array => $section->getAttributes())->all(),
        );

        return HomepageSection::hydrate($attributes)->each(function (HomepageSection $section): void {
            if (filled($section->cta_url)) {
                $section->setAttribute('cta_url', PublicUrl::resolve($section->cta_url));
            }
        });
    }

    /** @return Collection<int, HomepageSection> */
    private function query(): Collection
    {
        if (! HomepageSection::query()->exists()) {
            return collect(['hero', 'whats_on', 'gallery', 'join'])->map(fn (string $key, int $index): HomepageSection => new HomepageSection([
                'section_key' => $key,
                'enabled' => true,
                'sort_order' => ($index + 1) * 10,
                'layout_variant' => 'default',
                'content_mode' => 'automatic',
                'empty_behavior' => $key === 'gallery' ? 'message' : 'hide',
            ]));
        }

        return HomepageSection::query()
            ->where('enabled', true)
            ->where(fn ($query) => $query->whereNull('visible_from')->orWhere('visible_from', '<=', now()))
            ->where(fn ($query) => $query->whereNull('visible_until')->orWhere('visible_until', '>', now()))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
