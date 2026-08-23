<?php

namespace App\Domain\Content\Queries;

use App\Domain\Content\Models\HomepageSection;
use Illuminate\Support\Collection;

final class VisibleHomepageSections
{
    /** @return Collection<int, HomepageSection> */
    public function get(): Collection
    {
        if (! HomepageSection::query()->exists()) {
            return collect(['hero', 'whats_on', 'gallery', 'join'])->map(fn (string $key, int $index): HomepageSection => new HomepageSection([
                'section_key' => $key,
                'enabled' => true,
                'sort_order' => ($index + 1) * 10,
                'layout_variant' => 'default',
                'content_mode' => 'automatic',
                'empty_behavior' => 'hide',
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
