<?php

namespace App\Providers;

use App\Domain\Accounts\Models\InstallationOwnership;
use App\Domain\Communication\Contracts\NewsletterDelivery;
use App\Domain\Communication\Services\MailNewsletterDelivery;
use App\Domain\Content\Models\CmsPage;
use App\Domain\Content\Models\FooterSection;
use App\Domain\Content\Models\HomepageSection;
use App\Domain\Content\Models\NavigationItem;
use App\Domain\Content\Models\NewsArticle;
use App\Domain\Content\Models\Testimonial;
use App\Domain\Content\Observers\PublicContentCacheObserver;
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
use App\Domain\Operations\Environment\StagingMode;
use App\Domain\Operations\Installation\Contracts\DatabaseConnectionTester;
use App\Domain\Operations\Installation\Contracts\EnvironmentWriter;
use App\Domain\Operations\Installation\Contracts\MailConnectionTester;
use App\Domain\Operations\Installation\EnvironmentFileWriter;
use App\Domain\Operations\Installation\InstallationState;
use App\Domain\Operations\Installation\PdoDatabaseConnectionTester;
use App\Domain\Operations\Installation\SymfonyMailConnectionTester;
use App\Domain\Operations\Maintenance\MaintenanceManager;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Scheduling\Contracts\FallbackRunner;
use App\Domain\Operations\Scheduling\Contracts\FallbackWorkload;
use App\Domain\Operations\Scheduling\RunFallbackWork;
use App\Domain\Operations\Scheduling\SchedulerHeartbeat;
use App\Domain\Operations\Scheduling\WaymarkFallbackWorkload;
use App\Domain\Operations\Updates\Contracts\UpdateEnvironmentProbe;
use App\Domain\Operations\Updates\NativeUpdateEnvironment;
use App\Domain\Walks\RelatedContent\RelatedWalks;
use App\Domain\Walks\RelatedContent\SignalRelatedWalks;
use App\Http\Middleware\RequireSensitiveActionAssurance;
use App\Policies\InstallationOwnershipPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Cookie\Middleware\EncryptCookies;
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
        $this->app->singleton(InstallationState::class, function (): InstallationState {
            $configuredState = config('waymark.installation.installed');
            $applicationKey = config('waymark.installation.legacy_application_key');

            return new InstallationState(
                (string) config('waymark.installation.lock_path'),
                is_bool($configuredState) ? $configuredState : null,
                is_string($applicationKey) ? $applicationKey : null,
            );
        });
        $this->app->bind(DatabaseConnectionTester::class, PdoDatabaseConnectionTester::class);
        $this->app->bind(MailConnectionTester::class, SymfonyMailConnectionTester::class);
        $this->app->bind(EnvironmentWriter::class, EnvironmentFileWriter::class);
        $this->app->bind(FallbackWorkload::class, WaymarkFallbackWorkload::class);
        $this->app->singleton(SchedulerHeartbeat::class, fn (): SchedulerHeartbeat => new SchedulerHeartbeat(
            (string) config('waymark.scheduler.heartbeat_path'),
            (int) config('waymark.scheduler.stale_after_minutes'),
        ));
        $this->app->singleton(FallbackRunner::class, fn (): FallbackRunner => new RunFallbackWork(
            app(SchedulerHeartbeat::class),
            app(FallbackWorkload::class),
            (string) config('waymark.scheduler.fallback_state_path'),
            (int) config('waymark.scheduler.fallback_cooldown_minutes'),
        ));
        $this->app->bind(RelatedWalks::class, SignalRelatedWalks::class);
        $this->app->bind(ImageMetadataReader::class, PhpExifImageMetadataReader::class);
        $this->app->bind(RasterImageTransformer::class, GdRasterImageTransformer::class);
        $this->app->bind(NewsletterDelivery::class, MailNewsletterDelivery::class);
        $this->app->bind(PublicFormChallenge::class, TurnstilePublicFormChallenge::class);
        $this->app->bind(UpdateEnvironmentProbe::class, NativeUpdateEnvironment::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        EncryptCookies::except([MaintenanceManager::BYPASS_COOKIE]);
        foreach ([CmsPage::class, NewsArticle::class, Event::class, SpecialAlbum::class, Document::class] as $model) {
            $model::observe(PublicSlugRedirectObserver::class);
        }
        foreach ([SiteProfile::class, NavigationItem::class, FooterSection::class, HomepageSection::class, Testimonial::class] as $model) {
            $model::observe(PublicContentCacheObserver::class);
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
        RateLimiter::for('recovery', fn (Request $request) => Limit::perHour(5)->by('recovery:'.$request->ip()));
        Gate::policy(InstallationOwnership::class, InstallationOwnershipPolicy::class);
        Livewire::addPersistentMiddleware([RequireSensitiveActionAssurance::class]);
        View::composer('components.public.site-header', fn ($view) => $view->with(['navigationItems' => app(PublicNavigationItems::class)->get(), 'branding' => app(PublicBranding::class)->get()]));
        View::composer('components.public.site-footer', fn ($view) => $view->with(['footerSections' => app(PublicFooterSections::class)->get(), 'branding' => app(PublicBranding::class)->get()]));
        View::composer('layouts.public', fn ($view) => $view->with([
            'branding' => app(PublicBranding::class)->get(),
            'analytics' => app(ExternalAnalytics::class)->forRequest(request()),
            'cookiePreferences' => app(ConsentPreferences::class)->forRequest(request()),
            'staging' => app(StagingMode::class)->active(),
        ]));

        $testNow = env('WAYMARK_TEST_NOW');

        if (app()->environment(['testing', 'browser-testing']) && is_string($testNow) && $testNow !== '') {
            Carbon::setTestNow(Carbon::parse($testNow, config('app.timezone')));
        }
    }
}
