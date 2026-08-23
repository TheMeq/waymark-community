<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Support\HomepageSectionRegistry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

#[Fillable(['section_key', 'enabled', 'sort_order', 'layout_variant', 'heading', 'supporting_copy', 'cta_label', 'cta_url', 'content_mode', 'pinned_type', 'pinned_id', 'visible_from', 'visible_until', 'empty_behavior', 'settings'])]
final class HomepageSection extends Model
{
    private const PINNED_TYPES = [
        'hero' => 'site_media',
        'whats_on' => 'event',
        'gallery' => 'community_photo',
        'join' => 'cms_page',
        'testimonial' => 'testimonial',
        'news' => 'news_article',
        'holiday' => 'event',
    ];

    protected static function booted(): void
    {
        self::saving(function (self $section): void {
            $layouts = HomepageSectionRegistry::sections()[$section->section_key] ?? null;
            if ($layouts === null || ! in_array($section->layout_variant, $layouts, true)) {
                throw ValidationException::withMessages(['section_key' => 'Choose an approved homepage section and layout.']);
            }
            if (! in_array($section->content_mode, ['automatic', 'pinned'], true) || ! in_array($section->empty_behavior, ['hide', 'message'], true)) {
                throw ValidationException::withMessages(['content_mode' => 'Choose an approved content and empty-state behaviour.']);
            }
            if ($section->content_mode === 'pinned' && (($section->pinned_type ?? null) !== (self::PINNED_TYPES[$section->section_key] ?? null) || (int) $section->pinned_id < 1)) {
                throw ValidationException::withMessages(['pinned_type' => 'Choose the approved content type and item for this section.']);
            }
            if (filled($section->cta_url) && ! NavigationItem::isAllowedUrl((string) $section->cta_url)) {
                throw ValidationException::withMessages(['cta_url' => 'Use a site path or a secure external URL.']);
            }
        });
    }

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'sort_order' => 'integer', 'visible_from' => 'datetime', 'visible_until' => 'datetime', 'settings' => 'array'];
    }
}
