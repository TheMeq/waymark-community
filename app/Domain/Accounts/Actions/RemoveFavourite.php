<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Models\Favourite;
use App\Domain\Accounts\Queries\FavouriteablePublicEventsQuery;
use App\Domain\Events\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

final readonly class RemoveFavourite
{
    public function __construct(private FavouriteablePublicEventsQuery $events) {}

    public function handle(User $user, Event $event): void
    {
        if (! $user->isActive()) {
            throw ValidationException::withMessages(['account' => 'Disabled accounts cannot change favourites.']);
        }
        if (! $this->events->isEligible($event)) {
            throw (new ModelNotFoundException)->setModel(Event::class, [$event->getKey()]);
        }

        $deleted = Favourite::query()
            ->where('user_id', $user->id)
            ->where('event_id', $event->id)
            ->delete();

        if ($deleted === 0) {
            throw (new ModelNotFoundException)->setModel(Favourite::class, [$event->getKey()]);
        }
    }
}
