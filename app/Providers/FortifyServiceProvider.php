<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\ViewModels\PublicAccountPageViewModel;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);
        Fortify::loginView(fn (Request $request): View => $this->accountView('auth.login', $request));
        Fortify::registerView(fn (Request $request): View => $this->accountView('auth.register', $request));
        Fortify::requestPasswordResetLinkView(fn (Request $request): View => $this->accountView('auth.forgot-password', $request));
        Fortify::resetPasswordView(fn (Request $request): View => $this->accountView('auth.reset-password', $request));
        Fortify::verifyEmailView(fn (Request $request): View => $this->accountView('auth.verify-email', $request));
        Fortify::twoFactorChallengeView(fn (Request $request): View => $this->accountView('auth.two-factor-challenge', $request));
        Fortify::confirmPasswordView(fn (Request $request): View => $this->accountView('auth.confirm-password', $request));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

    }

    private function accountView(string $view, Request $request): View
    {
        $page = PublicAccountPageViewModel::current();

        return view($view, [
            'request' => $request,
            'site' => $page->site,
            'theme' => $page->theme,
        ]);
    }
}
