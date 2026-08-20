<?php

namespace App\Providers;

use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
