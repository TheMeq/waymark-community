<?php

namespace App\Domain\Content\Queries;

use App\Domain\Content\Models\HomepageSection;
use App\Domain\Content\Models\NewsArticle;
use Illuminate\Support\Collection;

final readonly class HomepageNews
{
    public function __construct(private PublicNews $news) {}

    /** @return Collection<int, NewsArticle> */
    public function get(HomepageSection $section, int $limit = 3): Collection
    {
        $automatic = $this->news->active()
            ->reorder()
            ->orderByDesc('featured_on_homepage')
            ->orderByDesc('publish_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        if ($section->content_mode !== 'pinned' || $section->pinned_type !== 'news_article') {
            return $automatic;
        }

        $pinned = $this->news->active()->whereKey($section->pinned_id)->first();

        return $pinned instanceof NewsArticle
            ? collect([$pinned])->concat($automatic)->unique('id')->take($limit)->values()
            : $automatic;
    }
}
