<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Installation\InstallationState;
use App\Domain\Operations\Scheduling\Contracts\FallbackRunner;
use App\Domain\Operations\Scheduling\FallbackRunResult;
use App\Http\Middleware\TriggerNonCriticalFallback;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class RequestFallbackTest extends TestCase
{
    public function test_only_safe_installed_requests_trigger_bounded_fallback_work(): void
    {
        config()->set('waymark.scheduler.request_fallback_enabled', true);
        $runner = new class implements FallbackRunner
        {
            public int $runs = 0;

            public function handle(string $trigger, ?Carbon $now = null): FallbackRunResult
            {
                $this->runs++;

                return new FallbackRunResult(true, $trigger);
            }
        };
        $middleware = new TriggerNonCriticalFallback(
            new InstallationState(storage_path('framework/testing/request-fallback.lock'), true, null),
            $runner,
        );

        $middleware->handle(Request::create('/', 'GET'), fn (): Response => new Response('ok'));
        $middleware->handle(Request::create('/contact', 'POST'), fn (): Response => new Response('ok'));

        $this->assertSame(1, $runner->runs);
    }

    public function test_fallback_failure_never_breaks_the_public_response(): void
    {
        config()->set('waymark.scheduler.request_fallback_enabled', true);
        $runner = new class implements FallbackRunner
        {
            public function handle(string $trigger, ?Carbon $now = null): FallbackRunResult
            {
                throw new \RuntimeException('Fallback failed.');
            }
        };
        $middleware = new TriggerNonCriticalFallback(
            new InstallationState(storage_path('framework/testing/request-fallback.lock'), true, null),
            $runner,
        );

        $response = $middleware->handle(Request::create('/', 'GET'), fn (): Response => new Response('public response'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('public response', $response->getContent());
    }
}
