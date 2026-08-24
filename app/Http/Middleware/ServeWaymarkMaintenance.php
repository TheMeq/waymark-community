<?php

namespace App\Http\Middleware;

use App\Domain\Operations\Maintenance\MaintenanceManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ServeWaymarkMaintenance
{
    public function __construct(private MaintenanceManager $maintenance) {}

    public function handle(Request $request, Closure $next): Response
    {
        $state = $this->maintenance->state();
        if ($state === null || $request->is('setup') || $request->is('setup/*') || $request->is('recovery') || $request->is('updates/activate') || $request->is('up') || $request->is('admin/system-health*')
            || $this->maintenance->validBypass($request->cookie(MaintenanceManager::BYPASS_COOKIE))) {
            return $next($request);
        }

        $retryAfter = 600;
        if (is_string($state['expected_return_at'] ?? null)) {
            $timestamp = strtotime($state['expected_return_at']);
            if ($timestamp !== false) {
                $retryAfter = max(60, $timestamp - time());
            }
        }

        return response()->view('maintenance', ['maintenance' => $state], Response::HTTP_SERVICE_UNAVAILABLE)
            ->header('Retry-After', (string) $retryAfter);
    }
}
