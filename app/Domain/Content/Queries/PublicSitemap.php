<?php

namespace App\Domain\Content\Queries;

use App\Domain\Gallery\Models\SpecialAlbum;
use App\Domain\Governance\Queries\AvailableDocuments;
use App\Domain\Holidays\Queries\PublicHolidaysQuery;
use App\Domain\Socials\Queries\PublicSocialsQuery;
use App\Domain\Walks\Queries\PublicWalksQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final readonly class PublicSitemap
{
    public function __construct(
        private PublicWalksQuery $walks,
        private PublicSocialsQuery $socials,
        private PublicHolidaysQuery $holidays,
        private PublicCmsPages $pages,
        private PublicNews $news,
        private AvailableDocuments $documents,
    ) {}

    /** @return Collection<int, array{url:string,last_modified:?string}> */
    public function entries(): Collection
    {
        $entries = collect([
            $this->entry(route('home')),
            $this->entry(route('events.index')),
            $this->entry(route('walks.index')),
            $this->entry(route('socials.index')),
            $this->entry(route('holidays.index')),
            $this->entry(route('news.index')),
            $this->entry(route('documents.index')),
            $this->entry(route('gallery.index')),
        ]);

        $entries = $entries->concat($this->walks->published()->get()->map(fn ($event) => $this->entry(route('walks.show', $event->slug), $event->updated_at?->toDateString())));
        $entries = $entries->concat($this->socials->published()->get()->map(fn ($event) => $this->entry(route('socials.show', $event->slug), $event->updated_at?->toDateString())));
        $entries = $entries->concat($this->holidays->published()->get()->map(fn ($event) => $this->entry(route('holidays.show', $event->slug), $event->updated_at?->toDateString())));
        $entries = $entries->concat($this->pages->query()->get()->map(fn ($page) => $this->entry(route('cms.show', $page->slug), $page->updated_at?->toDateString())));
        $entries = $entries->concat($this->news->active()->get()->map(fn ($article) => $this->entry(route('news.show', $article->slug), $article->updated_at?->toDateString())));
        $entries = $entries->concat($this->documents->query(null)->get()->map(fn ($document) => $this->entry(route('documents.show', $document->slug), $document->updated_at?->toDateString())));
        $entries = $entries->concat(SpecialAlbum::query()->whereHas('photos', fn (Builder $photos): Builder => $photos->where('moderation_status', 'approved')->where('processing_status', 'complete')->whereNotNull('published_at')->where('published_at', '<=', now()))->get()->map(fn ($album) => $this->entry(route('gallery.albums.show', $album), $album->updated_at?->toDateString())));

        return $entries->unique('url')->sortBy('url')->values();
    }

    /** @return array{url:string,last_modified:?string} */
    private function entry(string $url, ?string $lastModified = null): array
    {
        return ['url' => $url, 'last_modified' => $lastModified];
    }
}
