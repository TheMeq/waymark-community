<?php

namespace App\Domain\Content\Queries;

use App\Domain\Content\Models\FooterSection;
use App\Domain\Content\Support\PublicContentCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

final class PublicFooterSections
{
    public function get(): Collection
    {
        if (! Schema::hasTable('footer_sections')) {
            return collect();
        }

        $attributes = Cache::remember(
            PublicContentCache::FOOTER,
            (int) config('waymark.public_cache_seconds', 300),
            fn (): array => FooterSection::query()->where('enabled', true)->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (FooterSection $section): array => $section->getAttributes())->all(),
        );

        return FooterSection::hydrate($attributes);
    }
}
