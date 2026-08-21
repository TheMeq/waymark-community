<?php

namespace App\Http\Controllers;

use App\Domain\Accounts\Security\SensitiveActionAssurance;
use App\Models\User;
use App\ViewModels\PublicAccountPageViewModel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

final class SensitiveTwoFactorConfirmationController extends Controller
{
    public function create(Request $request, SensitiveActionAssurance $assurance): View
    {
        $user = $this->enabledUser($request, $assurance);
        $page = PublicAccountPageViewModel::current();

        return view('account.security.confirm-sensitive-two-factor', [
            'site' => $page->site,
            'theme' => $page->theme,
            'email' => $user->email,
        ]);
    }

    public function store(
        Request $request,
        SensitiveActionAssurance $assurance,
        TwoFactorAuthenticationProvider $twoFactorProvider,
    ): RedirectResponse {
        $user = $this->enabledUser($request, $assurance);
        $code = $request->input('code');

        $isValid = is_string($code)
            && $code !== ''
            && $twoFactorProvider->verify(
                Fortify::currentEncrypter()->decrypt($user->two_factor_secret),
                $code,
            );

        if (! $isValid) {
            return back()->withErrors(['code' => 'The authentication code was invalid.']);
        }

        $assurance->recordSecondFactorConfirmation($user, $request->session());

        return redirect()->intended(route('home'));
    }

    private function enabledUser(Request $request, SensitiveActionAssurance $assurance): User
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User
                && $assurance->hasRecentPasswordConfirmation($user, $request->session())
                && $assurance->requiresSecondFactor($user),
            403,
        );

        return $user;
    }
}
