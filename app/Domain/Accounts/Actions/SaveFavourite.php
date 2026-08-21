<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Models\Favourite;
use App\Domain\Accounts\Queries\FavouriteablePublicEventsQuery;
use App\Domain\Events\Models\Event;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

final readonly class SaveFavourite
{
    /** @param null|Closure(User, Event): Favourite $createFavourite */
    public function __construct(
        private FavouriteablePublicEventsQuery $events,
        private ?Closure $createFavourite = null,
    ) {}

    public function handle(User $user, Event $event): Favourite
    {
        if (! $user->isActive()) {
            throw ValidationException::withMessages(['account' => 'Disabled accounts cannot save favourites.']);
        }
        if (! $this->events->isEligible($event)) {
            throw (new ModelNotFoundException)->setModel(Event::class, [$event->getKey()]);
        }

        try {
            return $this->firstOrCreate($user, $event);
        } catch (UniqueConstraintViolationException $exception) {
            return Favourite::query()
                ->where('user_id', $user->id)
                ->where('event_id', $event->id)
                ->firstOrFail();
        }
    }

    private function firstOrCreate(User $user, Event $event): Favourite
    {
        if ($this->createFavourite !== null) {
            return ($this->createFavourite)($user, $event);
        }

        return Favourite::query()->firstOrCreate([
            'user_id' => $user->id,
            'event_id' => $event->id,
        ]);
    }
}
