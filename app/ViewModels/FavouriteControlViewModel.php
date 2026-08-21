<?php

namespace App\ViewModels;

use App\Domain\Accounts\Models\Favourite;
use App\Domain\Accounts\Queries\FavouriteablePublicEventsQuery;
use App\Domain\Events\Models\Event;
use App\Models\User;

final readonly class FavouriteControlViewModel
{
    /** @return array{is_visible: bool, is_guest: bool, is_saved: bool, save_url: ?string, remove_url: ?string} */
    public static function for(Event $event, ?User $user, FavouriteablePublicEventsQuery $events): array
    {
        if (! $events->isEligible($event)) {
            return [
                'is_visible' => false,
                'is_guest' => false,
                'is_saved' => false,
                'save_url' => null,
                'remove_url' => null,
            ];
        }

        if ($user === null) {
            return [
                'is_visible' => true,
                'is_guest' => true,
                'is_saved' => false,
                'save_url' => null,
                'remove_url' => null,
            ];
        }

        $isSaved = Favourite::query()
            ->where('user_id', $user->id)
            ->where('event_id', $event->id)
            ->exists();

        return [
            'is_visible' => true,
            'is_guest' => false,
            'is_saved' => $isSaved,
            'save_url' => $isSaved ? null : route('favourites.store', $event),
            'remove_url' => $isSaved ? route('favourites.destroy', $event) : null,
        ];
    }
}
