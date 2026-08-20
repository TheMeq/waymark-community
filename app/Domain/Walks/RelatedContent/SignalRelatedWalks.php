<?php

namespace App\Domain\Walks\RelatedContent;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class SignalRelatedWalks implements RelatedWalks
{
    /** @return Collection<int, Event> */
    public function for(Event $event, int $limit = 3): Collection
    {
        $event->loadMissing('walk.tags');
        $tagIds = $event->walk?->tags->modelKeys() ?? [];
        $location = $this->normalisedLocation($event->walk?->meeting_location_name);

        if ($tagIds === [] && $location === null) {
            return new Collection;
        }

        $query = Event::query()
            ->select('events.*')
            ->where('type', EventType::Walk)
            ->where('is_public', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereIn('status', [EventStatus::Published, EventStatus::Changed])
            ->whereKeyNot($event->getKey())
            ->whereHas('walk')
            ->where(function (Builder $query) use ($tagIds, $location): void {
                if ($tagIds !== []) {
                    $query->whereHas('walk.tags', fn (Builder $tags): Builder => $tags->whereIn('tags.id', $tagIds));
                }

                if ($location !== null) {
                    $method = $tagIds === [] ? 'whereHas' : 'orWhereHas';
                    $query->{$method}('walk', fn (Builder $walks): Builder => $walks->whereRaw('LOWER(meeting_location_name) = ?', [$location]));
                }
            })
            ->with(['walk.grade', 'walk.primaryLeader', 'walk.tags']);

        if ($tagIds === []) {
            $query->selectRaw('0 as related_tag_matches');
        } else {
            $query->selectSub(
                DB::table('tag_walk')
                    ->join('walks', 'walks.id', '=', 'tag_walk.walk_id')
                    ->selectRaw('count(*)')
                    ->whereColumn('walks.event_id', 'events.id')
                    ->whereIn('tag_walk.tag_id', $tagIds),
                'related_tag_matches',
            );
        }

        if ($location === null) {
            $query->selectRaw('0 as related_location_matches');
        } else {
            $query->selectSub(
                DB::table('walks')
                    ->selectRaw('count(*)')
                    ->whereColumn('walks.event_id', 'events.id')
                    ->whereRaw('LOWER(meeting_location_name) = ?', [$location]),
                'related_location_matches',
            );
        }

        return $query
            ->orderByDesc('related_tag_matches')
            ->orderByDesc('related_location_matches')
            ->orderByDesc('starts_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    private function normalisedLocation(?string $location): ?string
    {
        $location = is_string($location) ? trim($location) : null;

        return $location === null || $location === '' ? null : mb_strtolower($location);
    }
}
