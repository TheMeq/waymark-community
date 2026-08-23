<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

#[Fillable(['section_key', 'heading', 'sort_order', 'enabled', 'links'])]
final class FooterSection extends Model
{
    protected static function booted(): void
    {
        self::saving(function (self $section): void {
            if (! in_array($section->section_key, ['explore', 'resources', 'legal', 'contact', 'social'], true) || count((array) $section->links) > 8) {
                throw ValidationException::withMessages(['section_key' => 'Choose a curated footer section with no more than eight links.']);
            }
            foreach ((array) $section->links as $link) {
                if (! is_array($link) || ! filled($link['label'] ?? null) || ! NavigationItem::isAllowedUrl((string) ($link['url'] ?? ''))) {
                    throw ValidationException::withMessages(['links' => 'Each footer link needs a label and safe URL.']);
                }
            }
        });
    }

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'links' => 'array', 'sort_order' => 'integer'];
    }
}
