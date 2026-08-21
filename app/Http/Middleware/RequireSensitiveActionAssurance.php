<?php

namespace App\Http\Middleware;

use App\Domain\Accounts\Security\SensitiveActionAssurance;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;

final readonly class RequireSensitiveActionAssurance
{
    public function __construct(private SensitiveActionAssurance $assurance) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        if (! $this->assurance->hasRecentPasswordConfirmation($user, $request->session())) {
            $this->assurance->forgetAll($request->session());

            return redirect()->guest(route('password.confirm'));
        }

        if (! $this->assurance->requiresSecondFactor($user)) {
            $this->assurance->forgetSecondFactorConfirmation($request->session());

            return $next($request);
        }

        if (! $this->assurance->hasRecentSecondFactorConfirmation($user, $request->session())) {
            $this->assurance->forgetSecondFactorConfirmation($request->session());

            return redirect()->guest(route('account.sensitive-confirmation.create'));
        }

        return $next($request);
    }
}
