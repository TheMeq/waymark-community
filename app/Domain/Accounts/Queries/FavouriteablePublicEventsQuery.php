<?php

namespace App\Domain\Accounts\Queries;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class FavouriteablePublicEventsQuery
{
    /** @return Builder<Event> */
    public function eligible(): Builder
    {
        return Event::query()
            ->whereNull('parent_event_id')
            ->whereIn('type', [EventType::Walk, EventType::Social, EventType::Holiday])
            ->where('is_public', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(function (Builder $events): void {
                $events->where(fn (Builder $events) => $events
                    ->where('type', EventType::Walk)
                    ->whereIn('status', self::walkAndSocialStatuses())
                    ->whereHas('walk'))
                    ->orWhere(fn (Builder $events) => $events
                        ->where('type', EventType::Social)
                        ->whereIn('status', self::walkAndSocialStatuses())
                        ->whereHas('social'))
                    ->orWhere(fn (Builder $events) => $events
                        ->where('type', EventType::Holiday)
                        ->whereIn('status', self::holidayStatuses())
                        ->whereHas('holiday'));
            });
    }

    public function isEligible(Event $event): bool
    {
        return $this->eligible()->whereKey($event)->exists();
    }

    /** @return Collection<int, Event> */
    public function forUser(User $user): Collection
    {
        return $this->eligible()
            ->whereHas('favourites', fn (Builder $favourites) => $favourites->where('user_id', $user->id))
            ->orderBy('type')
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();
    }

    /** @return list<EventStatus> */
    private static function walkAndSocialStatuses(): array
    {
        return [
            EventStatus::Published,
            EventStatus::Changed,
            EventStatus::Postponed,
            EventStatus::Cancelled,
            EventStatus::Completed,
        ];
    }

    /** @return list<EventStatus> */
    private static function holidayStatuses(): array
    {
        return [
            EventStatus::Published,
            EventStatus::Changed,
            EventStatus::Postponed,
            EventStatus::Cancelled,
        ];
    }
}
