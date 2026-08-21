<?php

namespace App\Http\Controllers;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Walks\Actions\DuplicateWalk;
use App\Domain\Walks\Models\Walk;
use App\Models\User;
use App\ViewModels\LeaderHubDuplicateWalkPageViewModel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class LeaderHubDuplicateWalkController extends Controller
{
    public function edit(Request $request, Walk $walk): View
    {
        $this->sourceFor($request, $walk);

        return view('leader-hub.duplicate', LeaderHubDuplicateWalkPageViewModel::from(
            $walk->loadMissing('event'),
        )->toArray());
    }

    public function store(Request $request, Walk $walk, DuplicateWalk $duplicateWalk): RedirectResponse
    {
        $leader = $this->sourceFor($request, $walk);
        $duplicateWalk->handle($walk, $leader, $request->all());

        return to_route('leader-hub.index')->with('status', 'Draft created.');
    }

    private function sourceFor(Request $request, Walk $walk): User
    {
        /** @var User $leader */
        $leader = $request->user();
        abort_unless(
            $leader->hasVerifiedEmail() && $leader->hasCapability(ModuleCapability::ManageOwnWalks),
            403,
        );

        $walk->loadMissing('event');
        abort_unless($walk->event->organiser_id === $leader->id, 404);

        return $leader;
    }
}
