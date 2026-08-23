<?php

namespace App\Domain\Content\Queries;

use App\Domain\Content\Data\PublicSearchFilters;
use App\Domain\Content\Data\PublicSearchResult;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Gallery\Models\SpecialAlbum;
use App\Domain\Governance\Models\Document;
use App\Domain\Governance\Queries\AvailableDocuments;
use App\Domain\Holidays\Queries\PublicHolidaysQuery;
use App\Domain\Socials\Queries\PublicSocialsQuery;
use App\Domain\Walks\Queries\PublicWalksQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final readonly class PublicSiteSearch
{
    public function __construct(
        private PublicWalksQuery $walks,
        private PublicSocialsQuery $socials,
        private PublicHolidaysQuery $holidays,
        private PublicCmsPages $pages,
        private PublicNews $news,
        private AvailableDocuments $documents,
    ) {}

    /** @return LengthAwarePaginator<PublicSearchResult> */
    public function search(PublicSearchFilters $filters, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        if (! $filters->hasCriteria()) {
            return new Paginator([], 0, $perPage, $page, ['path' => url('/search')]);
        }

        $results = collect();
        if ($this->includes($filters, 'event')) {
            $results = $results->concat($this->events($filters));
        }
        if ($this->includes($filters, 'page')) {
            $results = $results->concat($this->pages($filters));
        }
        if ($this->includes($filters, 'news')) {
            $results = $results->concat($this->news($filters));
        }
        if ($this->includes($filters, 'document')) {
            $results = $results->concat($this->documents($filters));
        }
        if ($this->includes($filters, 'album')) {
            $results = $results->concat($this->albums($filters));
        }

        $ordered = $results->sortBy([
            fn (PublicSearchResult $result): int => $result->date === null ? 1 : 0,
            fn (PublicSearchResult $result): string => $result->date ?? '',
            fn (PublicSearchResult $result): string => Str::lower($result->title),
        ])->values();

        return new Paginator(
            $ordered->forPage($page, $perPage)->values(),
            $ordered->count(),
            $perPage,
            $page,
            ['path' => url('/search'), 'query' => request()->query()],
        );
    }

    /** @return Collection<int, PublicSearchResult> */
    private function events(PublicSearchFilters $filters): Collection
    {
        if ($filters->documentCategory !== null) {
            return collect();
        }

        $queries = $filters->difficulty !== null || $filters->location !== null
            ? [$this->walks->published()]
            : [$this->walks->published(), $this->socials->published(), $this->holidays->published()];

        return collect($queries)->flatMap(function (Builder $query) use ($filters): Collection {
            $query->when($filters->dateFrom, fn (Builder $query, $date): Builder => $query->where('starts_at', '>=', $date))
                ->when($filters->dateTo, fn (Builder $query, $date): Builder => $query->where('starts_at', '<=', $date));
            $this->match($query, $filters->term, ['title', 'summary', 'description']);
            if ($filters->difficulty !== null) {
                $this->matchRelation($query, 'walk.grade', $filters->difficulty, ['name']);
            }
            if ($filters->location !== null) {
                $this->matchRelation($query, 'walk', $filters->location, ['meeting_location_name', 'meeting_address', 'meeting_postcode']);
            }

            return $query->limit(100)->get()->map(fn (Event $event): PublicSearchResult => new PublicSearchResult(
                type: 'event',
                label: match ($event->type) {
                    EventType::Walk => 'Walk', EventType::Social => 'Social', EventType::Holiday => 'Weekend away'
                },
                title: $event->title,
                url: match ($event->type) {
                    EventType::Walk => route('walks.show', $event->slug), EventType::Social => route('socials.show', $event->slug), EventType::Holiday => route('holidays.show', $event->slug)
                },
                summary: $event->summary,
                date: $event->starts_at?->toDateString(),
            ));
        })->unique(fn (PublicSearchResult $result): string => $result->type.'|'.$result->url)->values();
    }

    /** @return Collection<int, PublicSearchResult> */
    private function pages(PublicSearchFilters $filters): Collection
    {
        if ($this->hasEventOrDocumentFilters($filters)) {
            return collect();
        }

        $query = $this->pages->query();
        $this->match($query, $filters->term, ['title', 'blocks']);

        return $query->limit(100)->get()->map(fn ($page): PublicSearchResult => new PublicSearchResult('page', 'Page', $page->title, route('cms.show', $page->slug), $this->excerpt($page->blocks)));
    }

    /** @return Collection<int, PublicSearchResult> */
    private function news(PublicSearchFilters $filters): Collection
    {
        if ($this->hasEventOrDocumentFilters($filters)) {
            return collect();
        }

        $query = $this->news->active();
        $this->match($query, $filters->term, ['title', 'summary', 'blocks', 'primary_category', 'tags']);

        return $query->limit(100)->get()->map(fn ($article): PublicSearchResult => new PublicSearchResult('news', 'News', $article->title, route('news.show', $article->slug), $article->summary, $article->publish_at?->toDateString()));
    }

    /** @return Collection<int, PublicSearchResult> */
    private function documents(PublicSearchFilters $filters): Collection
    {
        if ($filters->dateFrom !== null || $filters->dateTo !== null || $filters->difficulty !== null || $filters->location !== null) {
            return collect();
        }

        $query = $this->documents->query(null)->with('category');
        $this->match($query, $filters->term, ['title', 'description']);
        if ($filters->documentCategory !== null) {
            $this->matchRelation($query, 'category', $filters->documentCategory, ['name', 'slug']);
        }

        return $query->limit(100)->get()->map(fn (Document $document): PublicSearchResult => new PublicSearchResult('document', 'Document', $document->title, route('documents.show', $document->slug), $document->description, $document->publication_date?->toDateString()));
    }

    /** @return Collection<int, PublicSearchResult> */
    private function albums(PublicSearchFilters $filters): Collection
    {
        if ($this->hasEventOrDocumentFilters($filters)) {
            return collect();
        }

        $query = SpecialAlbum::query()->whereHas('photos', fn (Builder $photos): Builder => $photos
            ->where('moderation_status', 'approved')->where('processing_status', 'complete')
            ->whereNotNull('published_at')->where('published_at', '<=', now()));
        $this->match($query, $filters->term, ['title', 'description']);

        return $query->limit(100)->get()->map(fn (SpecialAlbum $album): PublicSearchResult => new PublicSearchResult('album', 'Gallery album', $album->title, route('gallery.albums.show', $album->slug), $album->description));
    }

    private function match(Builder $query, ?string $term, array $columns): void
    {
        if ($term === null) {
            return;
        }
        $needle = '%'.Str::lower($term).'%';
        $query->where(function (Builder $query) use ($columns, $needle): void {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                $query->{$method}('LOWER(COALESCE('.$column.", '')) LIKE ?", [$needle]);
            }
        });
    }

    private function matchRelation(Builder $query, string $relation, string $term, array $columns): void
    {
        $query->whereHas($relation, function (Builder $relationQuery) use ($term, $columns): void {
            $this->match($relationQuery, $term, $columns);
        });
    }

    private function includes(PublicSearchFilters $filters, string $type): bool
    {
        return $filters->type === null || $filters->type === $type;
    }

    private function hasEventOrDocumentFilters(PublicSearchFilters $filters): bool
    {
        return $filters->dateFrom !== null || $filters->dateTo !== null || $filters->difficulty !== null
            || $filters->location !== null || $filters->documentCategory !== null;
    }

    private function excerpt(array $blocks): ?string
    {
        foreach ($blocks as $block) {
            foreach (['content', 'body'] as $field) {
                if (is_string($block[$field] ?? null) && trim(strip_tags($block[$field])) !== '') {
                    return Str::limit(trim(strip_tags($block[$field])), 180);
                }
            }
        }

        return null;
    }
}
