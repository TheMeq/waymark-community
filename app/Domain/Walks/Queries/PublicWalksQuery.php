<?php

namespace App\Domain\Walks\Queries;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Walks\Data\PublicWalkFilters;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class PublicWalksQuery
{
    /** @return Builder<Event> */
    public function upcoming(): Builder
    {
        return Event::query()
            ->where('type', EventType::Walk)
            ->where('is_public', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(function (Builder $query): void {
                $query->whereNull('completion_override')->orWhere('completion_override', false);
            })
            ->whereIn('status', [
                EventStatus::Published,
                EventStatus::Changed,
                EventStatus::Postponed,
                EventStatus::Cancelled,
            ])
            ->where('starts_at', '>=', now())
            ->whereHas('walk')
            ->with(['walk.grade', 'walk.primaryLeader'])
            ->orderBy('starts_at')
            ->orderBy('id');
    }

    /** @return Builder<Event> */
    public function published(): Builder
    {
        return Event::query()
            ->where('type', EventType::Walk)
            ->where('is_public', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereIn('status', [
                EventStatus::Published,
                EventStatus::Changed,
                EventStatus::Postponed,
                EventStatus::Cancelled,
                EventStatus::Completed,
            ])
            ->whereHas('walk')
            ->with(['walk.grade', 'walk.primaryLeader', 'walk.coLeaders', 'walk.tags']);
    }

    /** @return Builder<Event> */
    public function weekend(): Builder
    {
        $query = $this->upcoming();

        if ($query->getModel()->getConnection()->getDriverName() === 'sqlite') {
            return $query->whereRaw("strftime('%w', starts_at) in ('0', '6')");
        }

        return $query->whereRaw('WEEKDAY(starts_at) in (5, 6)');
    }

    /** @return Collection<int, User> */
    public function upcomingLeaders(): Collection
    {
        return $this->upcoming()
            ->with('walk.coLeaders')
            ->get()
            ->flatMap(fn (Event $event): array => [
                $event->walk?->primaryLeader,
                ...($event->walk?->coLeaders->all() ?? []),
            ])
            ->filter()
            ->unique('id')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /** @return Builder<Event> */
    public function filteredUpcoming(PublicWalkFilters $filters): Builder
    {
        $query = $this->upcoming();

        if ($filters->dateFrom !== null) {
            $query->where('starts_at', '>=', $filters->dateFrom->startOfDay());
        }

        if ($filters->dateTo !== null) {
            $query->where('starts_at', '<=', $filters->dateTo->endOfDay());
        }

        if ($filters->minimumDistance !== null) {
            $query->whereHas('walk', fn (Builder $walks) => $walks->where('distance', '>=', $filters->minimumDistance));
        }

        if ($filters->maximumDistance !== null) {
            $query->whereHas('walk', fn (Builder $walks) => $walks->where('distance', '<=', $filters->maximumDistance));
        }

        if ($filters->minimumAscent !== null) {
            $query->whereHas('walk', fn (Builder $walks) => $walks->where('ascent', '>=', $filters->minimumAscent));
        }

        if ($filters->maximumAscent !== null) {
            $query->whereHas('walk', fn (Builder $walks) => $walks->where('ascent', '<=', $filters->maximumAscent));
        }

        if ($filters->gradeId !== null) {
            $query->whereHas('walk', fn (Builder $walks) => $walks->where('grade_id', $filters->gradeId));
        }

        if ($filters->leaderId !== null) {
            $query->whereHas('walk', fn (Builder $walks) => $walks->where(function (Builder $leaders) use ($filters): void {
                $leaders->where('primary_leader_id', $filters->leaderId)
                    ->orWhereHas('coLeaders', fn (Builder $coLeaders) => $coLeaders->where('users.id', $filters->leaderId));
            }));
        }

        if ($filters->location !== null) {
            $location = '%'.$filters->location.'%';
            $query->whereHas('walk', fn (Builder $walks) => $walks->where(function (Builder $walks) use ($location): void {
                $walks->where('meeting_location_name', 'like', $location)
                    ->orWhere('meeting_address', 'like', $location)
                    ->orWhere('meeting_postcode', 'like', $location);
            }));
        }

        if ($filters->tagIds !== []) {
            $query->whereHas('walk.tags', fn (Builder $tags) => $tags->whereIn('tags.id', $filters->tagIds));
        }

        if ($filters->publicTransport) {
            $query->whereHas('walk', fn (Builder $walks) => $walks->where('is_public_transport_friendly', true));
        }

        if ($filters->timeGrouping === 'evening') {
            $query->whereTime('starts_at', '>=', '17:00:00');
        }

        if ($filters->timeGrouping === 'weekend') {
            if ($query->getModel()->getConnection()->getDriverName() === 'sqlite') {
                $query->whereRaw("strftime('%w', starts_at) in ('0', '6')");
            } else {
                $query->whereRaw('WEEKDAY(starts_at) in (5, 6)');
            }
        }

        return $query;
    }
}
