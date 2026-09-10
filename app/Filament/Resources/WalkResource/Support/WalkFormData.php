<?php

namespace App\Filament\Resources\WalkResource\Support;

use App\Domain\Content\Presentation\PublicImageReference;
use App\Domain\SiteMedia\SiteMediaPresenter;
use App\Domain\Walks\Models\Walk;
use Illuminate\Support\Arr;

final class WalkFormData
{
    /** @return array<string, mixed> */
    public static function from(Walk $walk): array
    {
        $walk->loadMissing(['event', 'coLeaders', 'tags', 'featuredMedia']);
        $managed = $walk->featuredMedia === null
            ? null
            : app(SiteMediaPresenter::class)->present($walk->featuredMedia, 'medium');
        $externalUrl = PublicImageReference::resolve($walk->featured_image_path);
        $source = $managed !== null ? 'managed' : ($externalUrl !== null ? 'external' : 'none');
        $preview = match ($source) {
            'managed' => [
                'source' => 'managed',
                'url' => $managed->url,
                'alt' => $walk->featured_image_alt_text ?: $managed->alt,
                'object_position' => $managed->objectPosition(),
                'fallback_url' => $externalUrl,
            ],
            'external' => [
                'source' => 'external',
                'url' => $externalUrl,
                'alt' => $walk->featured_image_alt_text ?: $walk->event->title.' featured image',
                'object_position' => null,
                'fallback_url' => null,
            ],
            default => null,
        };

        return [
            ...Arr::except($walk->attributesToArray(), [
                'featured_image_media_id',
                'featured_image_path',
                'featured_image_alt_text',
            ]),
            ...$walk->event->only(['title', 'slug', 'summary', 'description', 'starts_at', 'ends_at']),
            'co_leader_ids' => $walk->coLeaders->modelKeys(),
            'tag_ids' => $walk->tags->modelKeys(),
            'featured_image_source' => $source,
            'featured_image_upload' => null,
            'featured_image_external_url' => $externalUrl,
            'featured_image_alt_text' => $walk->featured_image_alt_text,
            'featured_image_remove_fallback' => $externalUrl === null ? 'clear' : 'reveal',
            'featured_image_preview' => $preview,
        ];
    }
}
