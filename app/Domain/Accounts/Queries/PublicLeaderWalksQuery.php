<?php

namespace App\Domain\Accounts\Queries;

use App\Domain\Events\Models\Event;
use App\Domain\Walks\Queries\PublicWalksQuery;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class PublicLeaderWalksQuery
{
    /** @return Builder<Event> */
    public function for(User $leader): Builder
    {
        return (new PublicWalksQuery)->upcoming()
            ->whereHas('walk', function (Builder $walks) use ($leader): void {
                $walks->where('primary_leader_id', $leader->id)
                    ->orWhereHas('coLeaders', fn (Builder $coLeaders) => $coLeaders->where('users.id', $leader->id));
            });
    }
}
