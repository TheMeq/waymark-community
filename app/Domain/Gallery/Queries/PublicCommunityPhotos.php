<?php

namespace App\Domain\Gallery\Queries;

use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\PublicCommunityPhotoPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

final class PublicCommunityPhotos
{
    public function __construct(private readonly PublicCommunityPhotoPresenter $presenter) {}

    /** @return Paginator<int, CommunityPhoto> */
    public function recent(int $page = 1, ?int $perPage = null): Paginator
    {
        return $this->paginate($this->base(), $page, $perPage, false);
    }

    /** @return Paginator<int, CommunityPhoto> */
    public function forEvent(int $eventId, int $page = 1, ?int $perPage = null): Paginator
    {
        return $this->paginate($this->base()->where('event_id', $eventId), $page, $perPage, true);
    }

    /** @return Paginator<int, CommunityPhoto> */
    public function forAlbum(int $albumId, int $page = 1, ?int $perPage = null): Paginator
    {
        return $this->paginate($this->base()->where('special_album_id', $albumId), $page, $perPage, true);
    }

    /** @return Collection<int, array{label:string,url:string,count:int,cover:CommunityPhoto}> */
    public function contexts(): Collection
    {
        return $this->eligibleForContext($this->base()->limit(max(1, (int) config('gallery.public.context_scan_limit', 72))))
            ->groupBy(fn (CommunityPhoto $photo): string => $photo->event_id !== null ? 'event:'.$photo->event_id : 'album:'.$photo->special_album_id)
            ->map(function (Collection $photos): array {
                $photo = $photos->first();
                $isEvent = $photo->event !== null;

                return [
                    'label' => $isEvent ? $photo->event->title : $photo->specialAlbum->title,
                    'url' => $isEvent ? route('gallery.events.show', $photo->event->slug) : route('gallery.albums.show', $photo->specialAlbum->slug),
                    'count' => $photos->count(),
                    'cover' => $photo,
                ];
            })->sortByDesc('count')->values();
    }

    /** @return Collection<int, CommunityPhoto> */
    public function eligibleForContext(Builder $query): Collection
    {
        return $query->with(['event:id,title,slug', 'specialAlbum:id,title,slug'])
            ->orderByRaw('CASE WHEN manual_sort_order IS NULL THEN 1 ELSE 0 END')
            ->orderBy('manual_sort_order')
            ->orderByRaw('COALESCE(captured_at, created_at) DESC')->orderByDesc('id')->get()
            ->filter(fn (CommunityPhoto $photo): bool => $this->presenter->isEligible($photo));
    }

    /** @return Builder<CommunityPhoto> */
    private function base(): Builder
    {
        return CommunityPhoto::query()->where('moderation_status', 'approved')->whereNotNull('published_at')->where('published_at', '<=', now())->where('processing_status', 'complete');
    }

    /** @param Builder<CommunityPhoto> $query @return Paginator<int, CommunityPhoto> */
    private function paginate(Builder $query, int $page, ?int $perPage, bool $contextOrder): Paginator
    {
        $perPage ??= max(1, (int) config('gallery.public.per_page', 18));
        $page = max(1, $page);
        Paginator::currentPageResolver(fn (): int => $page);
        $photos = $query->with(['event:id,title,slug', 'specialAlbum:id,title,slug'])
            ->when($contextOrder, fn (Builder $query) => $query->orderByRaw('CASE WHEN manual_sort_order IS NULL THEN 1 ELSE 0 END')->orderBy('manual_sort_order'))
            ->orderByRaw('COALESCE(captured_at, created_at) DESC')
            ->orderByDesc('id')
            ->simplePaginate($perPage, ['*'], 'page', $page);
        $photos->setCollection($photos->getCollection()->filter(fn (CommunityPhoto $photo): bool => $this->presenter->isEligible($photo))->values());

        return $photos;
    }
}
