<?php

namespace App\Http\Controllers;

use App\Domain\Accounts\Actions\UpdateLeaderProfile;
use App\Domain\Accounts\Data\LeaderProfileData;
use App\Http\Requests\UpdateLeaderProfileRequest;
use App\Models\User;
use App\ViewModels\LeaderProfileSettingsPageViewModel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class LeaderProfileSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $leader = $this->leader($request);

        return view('leader-hub.profile', LeaderProfileSettingsPageViewModel::for($leader)->toArray());
    }

    public function update(UpdateLeaderProfileRequest $request, UpdateLeaderProfile $updateLeaderProfile): RedirectResponse
    {
        $leader = $this->leader($request);
        $updateLeaderProfile->handle($leader, LeaderProfileData::fromRequest($request));

        return to_route('leader-hub.profile.edit')->with('status', 'Your leader profile settings have been saved.');
    }

    private function leader(Request $request): User
    {
        /** @var User $leader */
        $leader = $request->user();
        Gate::authorize('manageLeaderHub', $leader);

        return $leader;
    }
}
