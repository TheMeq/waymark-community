<?php

namespace App\Providers;

use App\Domain\Accounts\Models\InstallationOwnership;
use App\Domain\Communication\Contracts\NewsletterDelivery;
use App\Domain\Communication\Services\MailNewsletterDelivery;
use App\Domain\Content\Queries\PublicFooterSections;
use App\Domain\Content\Queries\PublicNavigationItems;
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
use Illuminate\Support\Facades\View;
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
        $this->app->bind(NewsletterDelivery::class, MailNewsletterDelivery::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('photo-upload', fn (Request $request) => [
            Limit::perMinute(12)->by('photo-upload:account:'.($request->user()?->id ?? 'guest')),
            Limit::perMinute(30)->by('photo-upload:ip:'.$request->ip()),
        ]);
        RateLimiter::for('photo-report', fn (Request $request) => [
            Limit::perMinute(6)->by('photo-report:account:'.($request->user()?->id ?? 'ip:'.$request->ip())),
            Limit::perMinute(12)->by('photo-report:ip:'.$request->ip()),
        ]);
        RateLimiter::for('contact', fn (Request $request) => Limit::perMinute(5)->by('contact:'.$request->ip()));
        Gate::policy(InstallationOwnership::class, InstallationOwnershipPolicy::class);
        Livewire::addPersistentMiddleware([RequireSensitiveActionAssurance::class]);
        View::composer('components.public.site-header', fn ($view) => $view->with('navigationItems', app(PublicNavigationItems::class)->get()));
        View::composer('components.public.site-footer', fn ($view) => $view->with('footerSections', app(PublicFooterSections::class)->get()));

        $testNow = env('WAYMARK_TEST_NOW');

        if (app()->environment(['testing', 'browser-testing']) && is_string($testNow) && $testNow !== '') {
            Carbon::setTestNow(Carbon::parse($testNow, config('app.timezone')));
        }
    }
}
