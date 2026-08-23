<?php

namespace App\Domain\Content\Queries;

use App\Domain\Content\Models\Testimonial;
use App\Domain\Content\Support\PublicContentCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class VisibleTestimonials
{
    public function get(): Collection
    {
        return Cache::remember(PublicContentCache::TESTIMONIALS, (int) config('waymark.public_cache_seconds', 300), fn (): Collection => Testimonial::query()->with('imageMedia')->where('active', true)->orderByDesc('featured')->orderBy('sort_order')->orderBy('id')->get());
    }
}
