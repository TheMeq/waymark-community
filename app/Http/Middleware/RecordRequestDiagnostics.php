<?php

namespace App\Http\Middleware;

use App\Domain\Operations\Diagnostics\RequestDiagnostics;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final readonly class RecordRequestDiagnostics
{
    public function __construct(private RequestDiagnostics $diagnostics) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('waymark.request_diagnostics.enabled')) {
            return $next($request);
        }

        $this->diagnostics->start();
        $response = $next($request);
        $report = $this->diagnostics->finish($request, $response->getStatusCode());

        $response->headers->set('Server-Timing', implode(', ', [
            'boot;dur='.$report['boot_ms'],
            'app;dur='.$report['application_ms'],
            'db;dur='.$report['query_time_ms'].';desc="'.$report['query_count'].' queries"',
            'external;dur='.$report['external_http_time_ms'],
        ]));
        Log::channel('performance')->info('request', $report);

        return $response;
    }
}
