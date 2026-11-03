<?php

namespace Tests\Feature\Operations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class RequestDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logPath = storage_path('framework/testing/request-diagnostics.log');
        @unlink($this->logPath);
        config()->set('logging.channels.performance.driver', 'single');
        config()->set('logging.channels.performance.path', $this->logPath);
        Http::fake(['diagnostics.example.test/*' => Http::response(['ok' => true])]);

        Route::middleware('web')->get('/request-diagnostics-probe', function () {
            Cache::remember('request-diagnostics-probe', 60, fn (): string => 'ready');
            DB::select('SELECT 1');
            DB::select('SELECT 1');
            Http::get('https://diagnostics.example.test/status');

            return response('profiled');
        })->name('request-diagnostics-probe');
    }

    public function test_development_diagnostics_record_request_database_cache_session_and_http_evidence(): void
    {
        config()->set('waymark.request_diagnostics.enabled', true);
        config()->set('cache.default', 'file');
        config()->set('session.driver', 'file');
        $response = $this->get('/request-diagnostics-probe');

        $response->assertSuccessful()
            ->assertHeader('Server-Timing');
        $this->assertStringContainsString('boot;dur=', (string) $response->headers->get('Server-Timing'));
        $this->assertStringContainsString('app;dur=', (string) $response->headers->get('Server-Timing'));
        $this->assertStringContainsString('db;dur=', (string) $response->headers->get('Server-Timing'));

        $log = (string) file_get_contents($this->logPath);
        $this->assertStringContainsString('request-diagnostics-probe', $log);
        $this->assertMatchesRegularExpression('/"query_count":\s*[2-9]/', $log);
        $this->assertStringContainsString('"repeated_queries"', $log);
        $this->assertStringContainsString('"cache_driver":"file"', $log);
        $this->assertStringContainsString('"session_driver":"file"', $log);
        $this->assertStringContainsString('"external_http_count":1', $log);
        $this->assertStringContainsString('diagnostics.example.test', $log);
    }

    public function test_diagnostics_are_completely_inactive_when_disabled(): void
    {
        config()->set('waymark.request_diagnostics.enabled', false);

        $response = $this->get('/request-diagnostics-probe');

        $response->assertSuccessful();
        $this->assertFalse($response->headers->has('Server-Timing'));
        $this->assertFileDoesNotExist($this->logPath);
    }
}
