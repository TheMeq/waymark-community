<?php

namespace App\Http\Middleware;

use App\Domain\Accounts\Security\SensitiveActionAssurance;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;

final readonly class RequireSensitivePasswordConfirmation
{
    public function __construct(private SensitiveActionAssurance $assurance) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        if (! $this->assurance->hasRecentPasswordConfirmation($user, $request->session())) {
            $this->assurance->forgetAll($request->session());
            $this->assurance->captureIntendedDestination($request);

            return to_route('password.confirm');
        }

        return $next($request);
    }
}
