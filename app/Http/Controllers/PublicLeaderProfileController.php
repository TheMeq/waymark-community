<?php

namespace App\Http\Controllers;

use App\Domain\Accounts\Queries\PublicLeaderWalksQuery;
use App\Models\User;
use App\ViewModels\PublicLeaderProfileViewModel;
use Illuminate\Contracts\View\View;

final class PublicLeaderProfileController extends Controller
{
    public function __invoke(string $slug, PublicLeaderWalksQuery $walks): View
    {
        $leader = User::query()
            ->where('public_profile_enabled', true)
            ->where('public_profile_slug', $slug)
            ->firstOrFail();

        abort_unless($leader->hasPublicLeaderProfile(), 404);

        return view('leaders.show', PublicLeaderProfileViewModel::from($leader, $walks->for($leader)->get())->toArray());
    }
}
