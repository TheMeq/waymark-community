<?php

namespace App\Http\Middleware;

use App\Domain\Operations\Installation\InstallationState;
use App\Domain\Operations\Scheduling\Contracts\FallbackRunner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class TriggerNonCriticalFallback
{
    public function __construct(
        private InstallationState $installation,
        private FallbackRunner $fallback,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ((bool) config('waymark.scheduler.request_fallback_enabled')
            && $this->installation->installed()
            && $request->isMethodSafe()) {
            try {
                $this->fallback->handle('request');
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $response;
    }
}
