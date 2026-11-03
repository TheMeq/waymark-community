<?php

namespace App\Http\Middleware;

use App\Domain\Communication\Support\OutboundEmailStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class GuardUnconfiguredEmailWorkflows
{
    private const array ROUTES_REQUIRING_EMAIL = [
        'password.email',
        'register.store',
        'verification.send',
        'contact.store',
    ];

    public function __construct(private OutboundEmailStatus $emailStatus) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->emailStatus->configured() || ! in_array($request->route()?->getName(), self::ROUTES_REQUIRING_EMAIL, true)) {
            return $next($request);
        }

        $message = $this->emailStatus->unavailableMessage();

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return back()->withErrors(['email' => $message])->withInput($request->except([
            'password',
            'password_confirmation',
        ]));
    }
}
