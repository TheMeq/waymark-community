<?php

namespace App\Domain\Events\Queries;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final readonly class PublicEventsQuery
{
    /** @return Builder<Event> */
    public function published(?EventType $type = null): Builder
    {
        return Event::query()
            ->with(['organiser', 'walk.grade', 'walk.primaryLeader', 'social', 'holiday'])
            ->where('is_public', true)
            ->whereNotNull('published_at')->where('published_at', '<=', now())
            ->whereIn('status', [EventStatus::Published, EventStatus::Changed, EventStatus::Postponed, EventStatus::Cancelled])
            ->when($type, fn (Builder $query, EventType $type): Builder => $query->where('type', $type))
            ->where(function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->where('type', EventType::Walk)->whereHas('walk');
                })->orWhere(function (Builder $query): void {
                    $query->where('type', EventType::Social)->whereHas('social');
                })->orWhere(function (Builder $query): void {
                    $query->where('type', EventType::Holiday)->whereHas('holiday');
                });
            })
            ->where(function (Builder $query): void {
                $query->whereNull('parent_event_id')
                    ->orWhereHas('parent.holiday', fn (Builder $query): Builder => $query->where('show_child_events_in_global_calendar', true));
            });
    }

    /** @return Builder<Event> */
    public function upcoming(?EventType $type = null): Builder
    {
        return $this->published($type)
            ->currentOrUpcoming()
            ->orderBy('starts_at')->orderBy('id');
    }

    /** @return Builder<Event> */
    public function forMonth(CarbonInterface $month, ?EventType $type = null): Builder
    {
        $start = $month->copy()->startOfMonth();
        $end = $start->copy()->addMonth();

        return $this->published($type)
            ->where('starts_at', '<', $end)
            ->where(function (Builder $query) use ($start): void {
                $query->where('ends_at', '>=', $start)
                    ->orWhere(function (Builder $query) use ($start): void {
                        $query->whereNull('ends_at')->where('starts_at', '>=', $start);
                    });
            })
            ->orderBy('starts_at')->orderBy('id');
    }
}
