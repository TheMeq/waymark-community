<?php

namespace App\Domain\Content\Queries;

use App\Domain\Content\Models\NewsArticle;
use Illuminate\Database\Eloquent\Builder;

final class PublicNews
{
    public function archive(): Builder
    {
        return NewsArticle::query()->with(['author', 'featuredMedia'])->where('publication_state', 'published')->where(fn (Builder $query) => $query->whereNull('publish_at')->orWhere('publish_at', '<=', now()))->orderByDesc('publish_at')->orderByDesc('id');
    }

    public function active(): Builder
    {
        return $this->archive()->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
