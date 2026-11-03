<?php

namespace App\Http\Middleware;

use App\Domain\Operations\Installation\InstallationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireWaymarkInstallation
{
    public function __construct(private InstallationState $installation) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('browser-testing') && $request->is('_dev/setup/*')) {
            return $next($request);
        }

        $setupRequest = $request->is('setup') || $request->is('setup/*');
        $recoveryRequest = $request->is('recovery');

        if ($this->installation->installed()) {
            abort_if($setupRequest, Response::HTTP_NOT_FOUND);

            return $next($request);
        }

        if ($setupRequest || $recoveryRequest) {
            if (config('session.driver') === 'database') {
                config()->set('session.driver', 'file');
            }

            return $next($request);
        }

        return redirect()->route('setup');
    }
}
