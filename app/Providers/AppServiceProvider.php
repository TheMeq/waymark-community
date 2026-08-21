<?php

namespace App\Providers;

use App\Domain\Accounts\Models\InstallationOwnership;
use App\Domain\Walks\RelatedContent\RelatedWalks;
use App\Domain\Walks\RelatedContent\SignalRelatedWalks;
use App\Http\Middleware\RequireSensitiveActionAssurance;
use App\Policies\InstallationOwnershipPolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

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
        Gate::policy(InstallationOwnership::class, InstallationOwnershipPolicy::class);
        Livewire::addPersistentMiddleware([RequireSensitiveActionAssurance::class]);

        $testNow = env('WAYMARK_TEST_NOW');

        if (app()->environment('testing') && is_string($testNow) && $testNow !== '') {
            Carbon::setTestNow(Carbon::parse($testNow, config('app.timezone')));
        }
    }
}
