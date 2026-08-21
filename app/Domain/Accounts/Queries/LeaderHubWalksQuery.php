<?php

namespace App\Domain\Accounts\Queries;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class LeaderHubWalksQuery
{
    /** @return Builder<Event> */
    public function drafts(User $leader): Builder
    {
        return $this->owned($leader)
            ->whereIn('status', [EventStatus::Draft, EventStatus::PendingApproval])
            ->orderBy('starts_at')
            ->orderBy('id');
    }

    /** @return Builder<Event> */
    public function currentOrUpcoming(User $leader): Builder
    {
        return $this->owned($leader)
            ->whereNotIn('status', [
                EventStatus::Draft,
                EventStatus::PendingApproval,
                EventStatus::Completed,
                EventStatus::Archived,
            ])
            ->currentOrUpcoming()
            ->orderBy('starts_at')
            ->orderBy('id');
    }

    /** @return Builder<Event> */
    public function past(User $leader): Builder
    {
        return $this->owned($leader)
            ->whereNotIn('status', [EventStatus::Draft, EventStatus::PendingApproval])
            ->where(function (Builder $events): void {
                $events->past()
                    ->orWhereIn('status', [EventStatus::Completed, EventStatus::Archived]);
            })
            ->orderByDesc('starts_at')
            ->orderByDesc('id');
    }

    /** @return Builder<Event> */
    private function owned(User $leader): Builder
    {
        return Event::query()
            ->where('type', EventType::Walk)
            ->where('organiser_id', $leader->id)
            ->whereHas('walk')
            ->with('walk');
    }
}
