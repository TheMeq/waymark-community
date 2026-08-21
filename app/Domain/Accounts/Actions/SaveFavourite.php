<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Models\Favourite;
use App\Domain\Accounts\Queries\FavouriteablePublicEventsQuery;
use App\Domain\Events\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;

final readonly class SaveFavourite
{
    public function __construct(private FavouriteablePublicEventsQuery $events) {}

    public function handle(User $user, Event $event): Favourite
    {
        if (! $this->events->isEligible($event)) {
            throw (new ModelNotFoundException)->setModel(Event::class, [$event->getKey()]);
        }

        try {
            return Favourite::query()->firstOrCreate([
                'user_id' => $user->id,
                'event_id' => $event->id,
            ]);
        } catch (QueryException $exception) {
            return Favourite::query()
                ->where('user_id', $user->id)
                ->where('event_id', $event->id)
                ->firstOrFail();
        }
    }
}
