<?php

namespace App\Domain\Socials\Queries;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use Illuminate\Database\Eloquent\Builder;

final class PublicSocialsQuery
{
    /** @return Builder<Event> */
    public function upcoming(): Builder
    {
        return $this->published()
            ->where(function (Builder $query): void {
                $query->whereNull('completion_override')->orWhere('completion_override', false);
            })
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->orderBy('id');
    }

    /** @return Builder<Event> */
    public function published(): Builder
    {
        return Event::query()
            ->where('type', EventType::Social)
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
            ->whereHas('social')
            ->with(['social', 'organiser']);
    }
}
