<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Support\HomepageSectionRegistry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

#[Fillable(['section_key', 'enabled', 'sort_order', 'layout_variant', 'heading', 'supporting_copy', 'cta_label', 'cta_url', 'content_mode', 'pinned_type', 'pinned_id', 'visible_from', 'visible_until', 'empty_behavior', 'settings'])]
final class HomepageSection extends Model
{
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
        });
    }

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'sort_order' => 'integer', 'visible_from' => 'datetime', 'visible_until' => 'datetime', 'settings' => 'array'];
    }
}
