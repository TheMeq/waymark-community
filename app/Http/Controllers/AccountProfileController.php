<?php

namespace App\Http\Controllers;

use App\Domain\Accounts\Actions\UpdateAccountProfile;
use App\Domain\Accounts\Data\AccountProfileData;
use App\Http\Requests\UpdateAccountProfileRequest;
use App\Models\User;
use App\ViewModels\ProfileSettingsPageViewModel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AccountProfileController extends Controller
{
    public function edit(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        Gate::authorize('updateProfile', $user);

        return view('account.profile.edit', ProfileSettingsPageViewModel::for($user)->toArray());
    }

    public function update(UpdateAccountProfileRequest $request, UpdateAccountProfile $updateAccountProfile): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        Gate::authorize('updateProfile', $user);

        $updateAccountProfile->handle($user, AccountProfileData::fromRequest($request));

        return to_route('account.profile.edit')->with('status', 'Your profile settings have been saved.');
    }
}
