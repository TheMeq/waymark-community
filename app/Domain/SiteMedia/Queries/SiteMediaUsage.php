<?php

namespace App\Domain\SiteMedia\Queries;

use App\Domain\Content\Models\CmsPage;
use App\Domain\Content\Models\NewsArticle;
use App\Domain\Content\Models\Testimonial;
use App\Domain\Governance\Models\CommitteeRole;
use App\Domain\Holidays\Models\Holiday;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Domain\Walks\Models\Walk;

final class SiteMediaUsage
{
    /** @return array<string> */
    public function labelsFor(SiteMedia $media): array
    {
        $labels = [];

        if (CmsPage::withTrashed()->where('hero_media_id', $media->id)->exists()) {
            $labels[] = 'CMS page hero';
        }
        if (NewsArticle::withTrashed()->where('featured_media_id', $media->id)->exists()) {
            $labels[] = 'News featured image';
        }
        if (Testimonial::query()->where('image_media_id', $media->id)->exists()) {
            $labels[] = 'Testimonial image';
        }
        if (CommitteeRole::query()->where('public_photo_media_id', $media->id)->exists()) {
            $labels[] = 'Committee role public photo';
        }
        if (Walk::withTrashed()->where('featured_image_media_id', $media->id)->exists()) {
            $labels[] = 'Walk featured image';
        }
        if (SiteProfile::query()->where('logo_media_id', $media->id)->exists()) {
            $labels[] = 'Site logo';
        }
        if (SiteProfile::query()->where('favicon_media_id', $media->id)->exists()) {
            $labels[] = 'Site favicon';
        }

        if (SiteProfile::query()->where('hero_media_id', $media->id)->exists()) {
            $labels[] = 'Site default hero';
        }

        if (Holiday::query()->where('featured_image_media_id', $media->id)->exists()) {
            $labels[] = 'Holiday featured image';
        }

        foreach ([CmsPage::class => 'Page content block', NewsArticle::class => 'News content block'] as $model => $label) {
            if ($model::withTrashed()->select(['id', 'blocks'])->where('blocks', 'like', '%'.$media->id.'%')->cursor()->contains(function ($owner) use ($media): bool {
                return collect((array) $owner->blocks)->contains(fn (array $block): bool => (int) ($block['media_id'] ?? 0) === (int) $media->id
                    || in_array((int) $media->id, array_map('intval', (array) ($block['media_ids'] ?? [])), true));
            })) {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    public function isUsed(SiteMedia $media): bool
    {
        return $this->labelsFor($media) !== [];
    }
}
