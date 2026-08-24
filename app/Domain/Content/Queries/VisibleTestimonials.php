<?php

namespace App\Domain\Content\Queries;

use App\Domain\Content\Models\Testimonial;
use App\Domain\Content\Support\PublicContentCache;
use App\Domain\SiteMedia\Models\SiteMedia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class VisibleTestimonials
{
    public function get(): Collection
    {
        $payloads = Cache::remember(
            PublicContentCache::TESTIMONIALS,
            (int) config('waymark.public_cache_seconds', 300),
            fn (): array => Testimonial::query()->with('imageMedia')->where('active', true)->orderByDesc('featured')->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (Testimonial $testimonial): array => [
                    'attributes' => $testimonial->getAttributes(),
                    'image_media' => $testimonial->imageMedia?->getAttributes(),
                ])->all(),
        );

        return collect($payloads)->map(function (array $payload): Testimonial {
            $testimonial = (new Testimonial)->newFromBuilder($payload['attributes']);
            $image = $payload['image_media'] === null
                ? null
                : (new SiteMedia)->newFromBuilder($payload['image_media']);

            return $testimonial->setRelation('imageMedia', $image);
        });
    }
}
