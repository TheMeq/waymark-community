<?php

namespace App\Providers;

use App\Domain\Accounts\Models\InstallationOwnership;
use App\Domain\Gallery\Contracts\ImageMetadataReader;
use App\Domain\Gallery\Contracts\RasterImageTransformer;
use App\Domain\Gallery\Services\GdRasterImageTransformer;
use App\Domain\Gallery\Services\PhpExifImageMetadataReader;
use App\Domain\Walks\RelatedContent\RelatedWalks;
use App\Domain\Walks\RelatedContent\SignalRelatedWalks;
use App\Http\Middleware\RequireSensitiveActionAssurance;
use App\Policies\InstallationOwnershipPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->app->bind(ImageMetadataReader::class, PhpExifImageMetadataReader::class);
        $this->app->bind(RasterImageTransformer::class, GdRasterImageTransformer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('photo-upload', fn (Request $request) => Limit::perMinute(12)->by(($request->user()?->id ?? 'guest').'|'.$request->ip()));
        Gate::policy(InstallationOwnership::class, InstallationOwnershipPolicy::class);
        Livewire::addPersistentMiddleware([RequireSensitiveActionAssurance::class]);

        $testNow = env('WAYMARK_TEST_NOW');

        if (app()->environment('testing') && is_string($testNow) && $testNow !== '') {
            Carbon::setTestNow(Carbon::parse($testNow, config('app.timezone')));
        }
    }
}
