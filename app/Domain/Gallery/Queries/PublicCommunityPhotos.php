<?php

namespace App\Domain\Gallery\Queries;

use App\Domain\Gallery\Data\PublicCommunityPhotoPage;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\PublicCommunityPhotoPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class PublicCommunityPhotos
{
    public function __construct(private readonly PublicCommunityPhotoPresenter $presenter) {}

    public function recent(?string $cursor = null, ?int $perPage = null): PublicCommunityPhotoPage
    {
        return $this->page($this->base(), $cursor, $perPage);
    }

    public function forEvent(int $eventId, ?string $cursor = null, ?int $perPage = null): PublicCommunityPhotoPage
    {
        return $this->page($this->base()->where('event_id', $eventId), $cursor, $perPage, true);
    }

    public function forAlbum(int $albumId, ?string $cursor = null, ?int $perPage = null): PublicCommunityPhotoPage
    {
        return $this->page($this->base()->where('special_album_id', $albumId), $cursor, $perPage, true);
    }

    /** @return Collection<int, array{label:string,url:string,count:int,cover:CommunityPhoto}> */
    public function contexts(): Collection
    {
        return $this->base()->selectRaw('event_id, special_album_id, COUNT(*) as approved_count')
            ->groupBy('event_id', 'special_album_id')->orderByDesc('approved_count')
            ->limit(max(1, (int) config('gallery.public.context_limit', 12)))->get()
            ->map(function (CommunityPhoto $row): ?array {
                $query = $this->base()->when($row->event_id !== null, fn (Builder $q) => $q->where('event_id', $row->event_id), fn (Builder $q) => $q->where('special_album_id', $row->special_album_id));
                $cover = $this->candidates($query, false)->limit(max(1, (int) config('gallery.public.context_cover_scan_limit', 24)))->get()
                    ->first(fn (CommunityPhoto $photo): bool => $this->presenter->isEligible($photo));
                if (! $cover instanceof CommunityPhoto) {
                    return null;
                }
                $isEvent = $cover->event !== null;

                return ['label' => $isEvent ? $cover->event->title : $cover->specialAlbum->title, 'url' => $isEvent ? route('gallery.events.show', $cover->event->slug) : route('gallery.albums.show', $cover->specialAlbum->slug), 'count' => (int) $row->approved_count, 'cover' => $cover];
            })->filter()->values();
    }

    private function page(Builder $query, ?string $cursor, ?int $perPage, bool $contextOrder = false): PublicCommunityPhotoPage
    {
        $perPage ??= max(1, (int) config('gallery.public.per_page', 18));
        $boundary = $this->decode($cursor);
        $items = collect();
        $lastAccepted = null;
        $lastInspected = null;
        $hasMore = false;
        $chunk = max($perPage * 3, 12);
        $maximumChunks = max(1, (int) config('gallery.public.maximum_scan_chunks', 8));
        for ($iteration = 0; $iteration < $maximumChunks; $iteration++) {
            $candidates = $this->candidates(clone $query, $contextOrder, $boundary)->limit($chunk)->get();
            if ($candidates->isEmpty()) {
                break;
            }
            foreach ($candidates as $photo) {
                $lastInspected = $this->cursorFor($photo);
                $presentation = $this->presenter->present($photo);
                if ($presentation === null) {
                    continue;
                }
                if ($items->count() === $perPage) {
                    $hasMore = true;
                    break 2;
                }
                $items->push($presentation);
                $lastAccepted = $lastInspected;
            }
            if ($candidates->count() < $chunk) {
                break;
            }
            $boundary = $lastInspected;
            $hasMore = true;
        }
        $next = $hasMore ? $this->encode($lastAccepted ?? $lastInspected) : null;

        return new PublicCommunityPhotoPage($items, $next);
    }

    /** @return Builder<CommunityPhoto> */
    private function base(): Builder
    {
        return CommunityPhoto::query()->where('moderation_status', 'approved')->whereNotNull('published_at')->where('published_at', '<=', now())->where('processing_status', 'complete');
    }

    /** @return Builder<CommunityPhoto> */
    private function candidates(Builder $query, bool $contextOrder, ?array $cursor = null): Builder
    {
        $query->with(['event:id,title,slug', 'specialAlbum:id,title,slug']);
        if ($cursor !== null) {
            $query->where(function (Builder $q) use ($cursor): void {
                $q->whereRaw('COALESCE(captured_at, created_at) < ?', [$cursor['at']])->orWhere(function (Builder $q) use ($cursor): void {
                    $q->whereRaw('COALESCE(captured_at, created_at) = ?', [$cursor['at']])->where('id', '<', $cursor['id']);
                });
            });
        }

        return $query->when($contextOrder, fn (Builder $q) => $q->orderByRaw('CASE WHEN manual_sort_order IS NULL THEN 1 ELSE 0 END')->orderBy('manual_sort_order'))->orderByRaw('COALESCE(captured_at, created_at) DESC')->orderByDesc('id');
    }

    /** @return array{at:string,id:int} */
    private function cursorFor(CommunityPhoto $photo): array
    {
        return ['at' => ($photo->captured_at ?? $photo->created_at)->format('Y-m-d H:i:s'), 'id' => $photo->id];
    }

    /** @return array{at:string,id:int}|null */
    private function decode(?string $cursor): ?array
    {
        $decoded = is_string($cursor) ? json_decode(base64_decode(strtr($cursor, '-_', '+/'), true) ?: '', true) : null;

        return is_array($decoded) && isset($decoded['at'], $decoded['id']) && is_string($decoded['at']) && is_int($decoded['id']) ? $decoded : null;
    }

    /** @param array{at:string,id:int}|null $cursor */
    private function encode(?array $cursor): ?string
    {
        return $cursor === null ? null : rtrim(strtr(base64_encode(json_encode($cursor, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }
}
