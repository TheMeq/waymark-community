<?php

namespace App\Domain\Operations\Diagnostics;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Request as LaravelRequest;
use Illuminate\Support\Facades\Event;

final class RequestDiagnostics
{
    private bool $active = false;

    private bool $listenersRegistered = false;

    private float $middlewareStartedAt = 0.0;

    /** @var list<array{sql: string, time_ms: float}> */
    private array $queries = [];

    /** @var array{hits: int, misses: int, writes: int, forgets: int} */
    private array $cache = ['hits' => 0, 'misses' => 0, 'writes' => 0, 'forgets' => 0];

    /** @var array<int, float> */
    private array $externalRequests = [];

    /** @var list<array{host: string, time_ms: float}> */
    private array $externalResponses = [];

    public function start(): void
    {
        $this->registerListeners();
        $this->active = true;
        $this->middlewareStartedAt = microtime(true);
        $this->queries = [];
        $this->cache = ['hits' => 0, 'misses' => 0, 'writes' => 0, 'forgets' => 0];
        $this->externalRequests = [];
        $this->externalResponses = [];
    }

    private function registerListeners(): void
    {
        if ($this->listenersRegistered) {
            return;
        }

        Event::listen(QueryExecuted::class, fn (QueryExecuted $event) => $this->recordQuery($event));
        foreach ([CacheHit::class, CacheMissed::class, KeyWritten::class, KeyForgotten::class] as $event) {
            Event::listen($event, fn (object $event) => $this->recordCache($event));
        }
        Event::listen(RequestSending::class, fn (RequestSending $event) => $this->recordExternalRequest($event));
        Event::listen(ResponseReceived::class, fn (ResponseReceived $event) => $this->recordExternalResponse($event));

        $this->listenersRegistered = true;
    }

    public function recordQuery(QueryExecuted $event): void
    {
        if (! $this->active) {
            return;
        }

        $this->queries[] = [
            'sql' => mb_substr((string) preg_replace('/\s+/', ' ', trim($event->sql)), 0, 300),
            'time_ms' => round((float) $event->time, 3),
        ];
    }

    public function recordCache(object $event): void
    {
        if (! $this->active) {
            return;
        }

        match (true) {
            $event instanceof CacheHit => $this->cache['hits']++,
            $event instanceof CacheMissed => $this->cache['misses']++,
            $event instanceof KeyWritten => $this->cache['writes']++,
            $event instanceof KeyForgotten => $this->cache['forgets']++,
            default => null,
        };
    }

    public function recordExternalRequest(RequestSending $event): void
    {
        if ($this->active) {
            $this->externalRequests[spl_object_id($event->request)] = microtime(true);
        }
    }

    public function recordExternalResponse(ResponseReceived $event): void
    {
        if (! $this->active) {
            return;
        }

        $startedAt = $this->externalRequests[spl_object_id($event->request)] ?? microtime(true);
        $handlerSeconds = $event->response->handlerStats()['total_time'] ?? null;
        $duration = is_numeric($handlerSeconds)
            ? (float) $handlerSeconds * 1000
            : (microtime(true) - $startedAt) * 1000;

        $this->externalResponses[] = [
            'host' => (string) parse_url($event->request->url(), PHP_URL_HOST),
            'time_ms' => round($duration, 3),
        ];
    }

    /** @return array<string, mixed> */
    public function finish(LaravelRequest $request, int $status): array
    {
        $finishedAt = microtime(true);
        $applicationMs = max(0.0, ($finishedAt - $this->middlewareStartedAt) * 1000);
        $frameworkStartedAt = defined('LARAVEL_START') ? (float) LARAVEL_START : $this->middlewareStartedAt;
        $bootMs = max(0.0, ($this->middlewareStartedAt - $frameworkStartedAt) * 1000);
        $queryGroups = collect($this->queries)->groupBy('sql');
        $externalTime = array_sum(array_column($this->externalResponses, 'time_ms'));

        $report = [
            'method' => $request->method(),
            'route' => $request->route()?->getName() ?? $request->route()?->uri() ?? $request->path(),
            'status' => $status,
            'total_ms' => round(max(0.0, ($finishedAt - $frameworkStartedAt) * 1000), 3),
            'boot_ms' => round($bootMs, 3),
            'application_ms' => round($applicationMs, 3),
            'render_ms' => null,
            'query_count' => count($this->queries),
            'query_time_ms' => round(array_sum(array_column($this->queries, 'time_ms')), 3),
            'slow_queries' => collect($this->queries)->sortByDesc('time_ms')->take(5)->values()->all(),
            'repeated_queries' => $queryGroups
                ->filter(fn ($queries): bool => $queries->count() > 1)
                ->map(fn ($queries, string $sql): array => ['sql' => $sql, 'count' => $queries->count()])
                ->take(5)
                ->values()
                ->all(),
            'cache_driver' => (string) config('cache.default'),
            'cache_events' => $this->cache,
            'session_driver' => (string) config('session.driver'),
            'session_started' => $request->hasSession(),
            'filesystem_touchpoints' => array_values(array_filter([
                config('cache.default') === 'file' ? 'file-cache' : null,
                config('session.driver') === 'file' ? 'file-session' : null,
            ])),
            'external_http_count' => count($this->externalResponses),
            'external_http_time_ms' => round($externalTime, 3),
            'external_http' => $this->externalResponses,
            'authenticated' => $request->user() !== null,
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ];

        $this->active = false;

        return $report;
    }
}
