<?php

namespace Tests\Feature\Discovery;

use App\Domain\Content\Models\PublicRedirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class RedirectPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();
        Route::get('/redirect-performance-one', fn () => 'one');
        Route::get('/redirect-performance-two', fn () => 'two');
    }

    public function test_redirect_lookup_uses_one_invalidatable_shared_host_cache_map(): void
    {
        $this->get('/redirect-performance-one')->assertSuccessful();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        $this->get('/redirect-performance-two')->assertSuccessful();

        $this->assertCount(0, collect($queries)->filter(
            fn (string $sql): bool => str_contains($sql, 'public_redirects') || str_contains($sql, 'sqlite_master'),
        ));

        PublicRedirect::query()->create([
            'source_path' => '/redirect-performance-two',
            'target_url' => '/walks',
            'status_code' => 302,
            'enabled' => true,
        ]);

        $this->get('/redirect-performance-two')->assertRedirect('/walks');
    }
}
