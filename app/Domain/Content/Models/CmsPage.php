<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Support\CmsBlockValidator;
use App\Domain\SiteMedia\Models\SiteMedia;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['title', 'slug', 'hero_media_id', 'blocks', 'publication_state', 'publish_at', 'archived_at', 'seo_title', 'seo_description'])]
final class CmsPage extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        self::saving(function (self $page): void {
            $page->blocks = app(CmsBlockValidator::class)->validate((array) $page->blocks);
        });
    }

    protected function casts(): array
    {
        return ['blocks' => 'array', 'publish_at' => 'datetime', 'archived_at' => 'datetime'];
    }

    public function heroMedia(): BelongsTo
    {
        return $this->belongsTo(SiteMedia::class, 'hero_media_id');
    }

    public function reviewLinks(): HasMany
    {
        return $this->hasMany(CmsReviewLink::class);
    }
}
