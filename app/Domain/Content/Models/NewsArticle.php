<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Support\CmsBlockValidator;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['title', 'slug', 'summary', 'blocks', 'featured_media_id', 'author_id', 'primary_category', 'tags', 'publication_state', 'publish_at', 'expires_at', 'featured_on_homepage', 'share_title', 'share_description'])]
final class NewsArticle extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        self::saving(fn (self $article) => $article->blocks = app(CmsBlockValidator::class)->validate((array) $article->blocks));
    }

    protected function casts(): array
    {
        return ['blocks' => 'array', 'tags' => 'array', 'publish_at' => 'datetime', 'expires_at' => 'datetime', 'featured_on_homepage' => 'boolean'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(SiteMedia::class, 'featured_media_id');
    }
}
