<?php

namespace App\Providers;

use App\Domain\Accounts\Models\InstallationOwnership;
use App\Domain\Communication\Contracts\NewsletterDelivery;
use App\Domain\Communication\Services\MailNewsletterDelivery;
use App\Domain\Content\Models\CmsPage;
use App\Domain\Content\Models\NewsArticle;
use App\Domain\Content\Observers\PublicSlugRedirectObserver;
use App\Domain\Content\Queries\PublicBranding;
use App\Domain\Content\Queries\PublicFooterSections;
use App\Domain\Content\Queries\PublicNavigationItems;
use App\Domain\Events\Models\Event;
use App\Domain\Gallery\Contracts\ImageMetadataReader;
use App\Domain\Gallery\Contracts\RasterImageTransformer;
use App\Domain\Gallery\Models\SpecialAlbum;
use App\Domain\Gallery\Services\GdRasterImageTransformer;
use App\Domain\Gallery\Services\PhpExifImageMetadataReader;
use App\Domain\Governance\Models\Document;
use App\Domain\Operations\Analytics\ConsentPreferences;
use App\Domain\Operations\Analytics\ExternalAnalytics;
use App\Domain\Operations\AntiSpam\PublicFormChallenge;
use App\Domain\Operations\AntiSpam\TurnstilePublicFormChallenge;
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
        $this->app->bind(PublicFormChallenge::class, TurnstilePublicFormChallenge::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([CmsPage::class, NewsArticle::class, Event::class, SpecialAlbum::class, Document::class] as $model) {
            $model::observe(PublicSlugRedirectObserver::class);
        }
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
        View::composer('components.public.site-header', fn ($view) => $view->with(['navigationItems' => app(PublicNavigationItems::class)->get(), 'branding' => app(PublicBranding::class)->get()]));
        View::composer('components.public.site-footer', fn ($view) => $view->with(['footerSections' => app(PublicFooterSections::class)->get(), 'branding' => app(PublicBranding::class)->get()]));
        View::composer('layouts.public', fn ($view) => $view->with([
            'branding' => app(PublicBranding::class)->get(),
            'analytics' => app(ExternalAnalytics::class)->forRequest(request()),
            'cookiePreferences' => app(ConsentPreferences::class)->forRequest(request()),
        ]));

        $testNow = env('WAYMARK_TEST_NOW');

        if (app()->environment(['testing', 'browser-testing']) && is_string($testNow) && $testNow !== '') {
            Carbon::setTestNow(Carbon::parse($testNow, config('app.timezone')));
        }
    }
}
