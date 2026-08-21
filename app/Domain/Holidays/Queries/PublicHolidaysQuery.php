<?php

namespace App\Domain\Holidays\Queries;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use Illuminate\Database\Eloquent\Builder;

final readonly class PublicHolidaysQuery
{
    /** @return Builder<Event> */
    public function upcoming(): Builder
    {
        return $this->published()
            ->currentOrUpcoming()
            ->orderBy('starts_at')->orderBy('id');
    }

    /** @return Builder<Event> */
    public function published(): Builder
    {
        return Event::query()
            ->with(['holiday', 'organiser'])
            ->where('type', EventType::Holiday)
            ->whereIn('status', [EventStatus::Published, EventStatus::Changed, EventStatus::Postponed, EventStatus::Cancelled])
            ->where('is_public', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
