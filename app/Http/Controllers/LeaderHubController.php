<?php

namespace App\Http\Controllers;

use App\Domain\Accounts\Queries\LeaderHubWalksQuery;
use App\Domain\Governance\Queries\LeaderHubDocuments;
use App\Models\User;
use App\ViewModels\LeaderHubPageViewModel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class LeaderHubController extends Controller
{
    public function __invoke(Request $request, LeaderHubWalksQuery $walks, LeaderHubDocuments $documents): View
    {
        /** @var User $leader */
        $leader = $request->user();
        Gate::authorize('manageLeaderHub', $leader);

        return view('leader-hub.index', LeaderHubPageViewModel::from(
            $leader,
            $walks->drafts($leader)->get(),
            $walks->currentOrUpcoming($leader)->get(),
            $walks->past($leader)->get(),
            $documents->get($leader),
        )->toArray());
    }
}
