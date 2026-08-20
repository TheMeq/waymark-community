<?php

namespace App\Providers;

use App\Domain\Walks\RelatedContent\RelatedWalks;
use App\Domain\Walks\RelatedContent\SignalRelatedWalks;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RelatedWalks::class, SignalRelatedWalks::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $testNow = env('WAYMARK_TEST_NOW');

        if (app()->environment('testing') && is_string($testNow) && $testNow !== '') {
            Carbon::setTestNow(Carbon::parse($testNow, config('app.timezone')));
        }
    }
}
