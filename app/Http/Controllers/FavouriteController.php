<?php

namespace App\Http\Controllers;

use App\Domain\Accounts\Actions\RemoveFavourite;
use App\Domain\Accounts\Actions\SaveFavourite;
use App\Domain\Accounts\Queries\FavouriteablePublicEventsQuery;
use App\Domain\Events\Models\Event;
use App\Models\User;
use App\ViewModels\FavouritesPageViewModel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class FavouriteController extends Controller
{
    public function index(Request $request, FavouriteablePublicEventsQuery $events): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('account.favourites.index', FavouritesPageViewModel::from($user, $events->forUser($user))->toArray());
    }

    public function store(Request $request, Event $event, SaveFavourite $saveFavourite): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $saveFavourite->handle($user, $event);

        return back();
    }

    public function destroy(Request $request, Event $event, RemoveFavourite $removeFavourite): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $removeFavourite->handle($user, $event);

        return back();
    }
}
