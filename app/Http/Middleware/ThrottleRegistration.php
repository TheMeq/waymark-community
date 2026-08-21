<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

final readonly class ThrottleRegistration
{
    public function __construct(private ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if (! $request->routeIs('register.store')) {
            return $next($request);
        }

        return $this->throttle->handle($request, $next, 'registration');
    }
}
