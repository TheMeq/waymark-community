<?php

use App\Domain\Operations\Installation\SetupStep;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Http\Controllers\AccountPrivacyController;
use App\Http\Controllers\AccountProfileController;
use App\Http\Controllers\AccountSecurityController;
use App\Http\Controllers\BackupDownloadController;
use App\Http\Controllers\BackupWorkController;
use App\Http\Controllers\BrandingPreviewController;
use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\CmsPageController;
use App\Http\Controllers\CommitteeHubController;
use App\Http\Controllers\CommunityPhotoModerationPreviewController;
use App\Http\Controllers\CommunityPhotoReportController;
use App\Http\Controllers\CommunityPhotoUploadController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CookieSettingsController;
use App\Http\Controllers\FavouriteController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LeaderHubController;
use App\Http\Controllers\LeaderHubDocumentDownloadController;
use App\Http\Controllers\LeaderHubDuplicateWalkController;
use App\Http\Controllers\LeaderProfileSettingsController;
use App\Http\Controllers\MaintenanceModeController;
use App\Http\Controllers\NewHereController;
use App\Http\Controllers\PublicCommitteeController;
use App\Http\Controllers\PublicDocumentController;
use App\Http\Controllers\PublicGalleryController;
use App\Http\Controllers\PublicHolidayAttachmentDownloadController;
use App\Http\Controllers\PublicHolidayIndexController;
use App\Http\Controllers\PublicHolidayShowController;
use App\Http\Controllers\PublicLeaderProfileController;
use App\Http\Controllers\PublicNewsController;
use App\Http\Controllers\PublicPolicyController;
use App\Http\Controllers\PublicSearchController;
use App\Http\Controllers\PublicSocialAttachmentDownloadController;
use App\Http\Controllers\PublicSocialIndexController;
use App\Http\Controllers\PublicSocialShowController;
use App\Http\Controllers\PublicWalkAttachmentDownloadController;
use App\Http\Controllers\PublicWalkGpxDownloadController;
use App\Http\Controllers\PublicWalkIndexController;
use App\Http\Controllers\PublicWalkShowController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\RecoveryController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SensitiveTwoFactorConfirmationController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SiteMediaStreamController;
use App\Http\Controllers\UpdateActivationController;
use App\Http\Controllers\UpdateContinuationController;
use App\Http\Controllers\UpdateInstallationController;
use App\Http\Controllers\UpdateRollbackController;
use App\Http\Controllers\WalkGradingGuideController;
use App\Http\Controllers\WhatsOnCalendarController;
use App\Http\Controllers\WhatsOnController;
use App\Http\Middleware\CaptureCampaignParameters;
use App\Http\Middleware\RecordAccountActivity;
use App\Http\Middleware\RequireActiveAccount;
use App\Http\Middleware\TriggerNonCriticalFallback;
use Illuminate\Support\Facades\Route;

Route::get('/setup', [SetupController::class, 'show'])->name('setup');
Route::post('/setup', [SetupController::class, 'start'])->name('setup.start');
Route::get('/setup/{step}', [SetupController::class, 'show'])
    ->whereIn('step', array_column(SetupStep::cases(), 'value'))
    ->name('setup.step');
Route::post('/setup/{step}', [SetupController::class, 'store'])
    ->whereIn('step', array_column(SetupStep::cases(), 'value'))
    ->name('setup.step.store');
Route::get('/recovery', [RecoveryController::class, 'show'])
    ->withoutMiddleware([CaptureCampaignParameters::class, RecordAccountActivity::class, RequireActiveAccount::class, TriggerNonCriticalFallback::class])
    ->name('recovery.show');
Route::post('/recovery', [RecoveryController::class, 'restore'])
    ->middleware('throttle:recovery')
    ->withoutMiddleware([CaptureCampaignParameters::class, RecordAccountActivity::class, RequireActiveAccount::class, TriggerNonCriticalFallback::class])
    ->name('recovery.restore');
Route::get('/updates/activate', [UpdateActivationController::class, 'show'])->name('updates.activate.show');
Route::post('/updates/activate', [UpdateActivationController::class, 'store'])->middleware('throttle:recovery')->name('updates.activate.store');
Route::post('/updates/rollback', UpdateRollbackController::class)->middleware('throttle:recovery')->name('updates.rollback.store');
Route::get('/', HomeController::class)->name('home');
Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/service-worker.js', [PwaController::class, 'serviceWorker'])->name('pwa.service-worker');
Route::get('/offline', [PwaController::class, 'offline'])->name('pwa.offline');
Route::get('/media/{media}/image/{variant}', SiteMediaStreamController::class)->whereNumber('media')->name('site-media.stream');
Route::get('/new-here', NewHereController::class)->name('new-here');
Route::get('/news', [PublicNewsController::class, 'index'])->name('news.index');
Route::get('/search', PublicSearchController::class)->name('search');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/news/{slug}', [PublicNewsController::class, 'show'])->name('news.show');
Route::get('/policies/{slug}', PublicPolicyController::class)->name('policies.show');
Route::get('/documents', [PublicDocumentController::class, 'index'])->name('documents.index');
Route::get('/documents/{slug}', [PublicDocumentController::class, 'show'])->name('documents.show');
Route::get('/documents/{slug}/versions/{version}/download', [PublicDocumentController::class, 'download'])->whereNumber('version')->name('documents.download');
Route::get('/committee', [PublicCommitteeController::class, 'index'])->name('committee.index');
Route::get('/committee/meetings', [PublicCommitteeController::class, 'meetings'])->name('committee.meetings');
Route::get('/contact', [ContactController::class, 'create'])->name('contact.create');
Route::get('/cookie-settings', [CookieSettingsController::class, 'edit'])->name('cookie-settings.edit');
Route::post('/cookie-settings', [CookieSettingsController::class, 'update'])->name('cookie-settings.update');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');
Route::get('/pages/{slug}', [CmsPageController::class, 'show'])->name('cms.show');
Route::get('/review/pages/{token}', [CmsPageController::class, 'review'])->name('cms.review');
Route::get('/photos', [PublicGalleryController::class, 'index'])->name('gallery.index');
Route::get('/photos/events/{event:slug}', [PublicGalleryController::class, 'event'])->name('gallery.events.show');
Route::get('/photos/holidays/{event:slug}', [PublicGalleryController::class, 'holiday'])->name('gallery.holidays.show');
Route::get('/photos/albums/{album:slug}', [PublicGalleryController::class, 'album'])->name('gallery.albums.show');
Route::get('/photos/{photo}/image/{variant}', [PublicGalleryController::class, 'image'])->whereNumber('photo')->name('gallery.photos.image');
Route::get('/photos/{photo}/download', [PublicGalleryController::class, 'download'])->whereNumber('photo')->name('gallery.photos.download');
Route::get('/photos/{photo}', [PublicGalleryController::class, 'show'])->whereNumber('photo')->name('gallery.photos.show');
Route::get('/leaders/{slug}', PublicLeaderProfileController::class)->name('leaders.show');
Route::get('/photos/{photo}/report', [CommunityPhotoReportController::class, 'create'])->whereNumber('photo')->name('community-photos.reports.create');
Route::post('/photos/{photo?}/report', CommunityPhotoReportController::class)->whereNumber('photo')->middleware('throttle:photo-report')->name('community-photos.reports.store');
Route::middleware('auth')->group(function (): void {
    Route::post('/admin/update-centre/install', UpdateInstallationController::class)->middleware('sensitive.confirmed')->name('admin.updates.install');
    Route::post('/admin/update-centre/continue', UpdateContinuationController::class)->middleware('sensitive.confirmed')->name('admin.updates.continue');
    Route::post('/admin/maintenance-mode/enable', [MaintenanceModeController::class, 'enable'])->middleware('sensitive.confirmed')->name('admin.maintenance.enable');
    Route::post('/admin/maintenance-mode/disable', [MaintenanceModeController::class, 'disable'])->middleware('sensitive.confirmed')->name('admin.maintenance.disable');
    Route::get('/admin/system-health/backups/{backup}/download', BackupDownloadController::class)
        ->middleware('sensitive.confirmed')->name('admin.backups.download');
    Route::post('/admin/system-health/backups/{backup}/advance', [BackupWorkController::class, 'advance'])->name('admin.backups.advance');
    Route::post('/admin/system-health/backups/{backup}/retry', [BackupWorkController::class, 'retry'])->name('admin.backups.retry');
    Route::get('/committee-hub', CommitteeHubController::class)->name('committee-hub.index');
    Route::get('/admin/branding-preview', BrandingPreviewController::class)->name('branding.preview');
    Route::get('/admin/pages/{page}/preview', [CmsPageController::class, 'preview'])->name('cms.preview');
    Route::get('/admin/photo-moderation/{photo}/preview', CommunityPhotoModerationPreviewController::class)->name('admin.photo-moderation.preview');
    Route::get('/photos/upload', [CommunityPhotoUploadController::class, 'create'])->name('community-photos.upload.create');
    Route::post('/photos/upload', [CommunityPhotoUploadController::class, 'store'])->middleware('throttle:photo-upload')->name('community-photos.upload.store');
    Route::delete('/photos/{photo}', [CommunityPhotoUploadController::class, 'destroy'])->name('community-photos.destroy');
    Route::post('/photos/{photo}/removal-request', [CommunityPhotoUploadController::class, 'requestRemoval'])->name('community-photos.removal-request.store');
    Route::get('/account/profile', [AccountProfileController::class, 'edit'])->name('account.profile.edit');
    Route::patch('/account/profile', [AccountProfileController::class, 'update'])->name('account.profile.update');
    Route::get('/account/privacy', [AccountPrivacyController::class, 'show'])->name('account.privacy.show');
    Route::post('/account/privacy/exports', [AccountPrivacyController::class, 'requestExport'])->name('account.privacy.exports.request');
    Route::post('/account/privacy/deletion', [AccountPrivacyController::class, 'requestDeletion'])
        ->middleware('sensitive.confirmed')->name('account.privacy.deletion.request');
    Route::get('/account/privacy/exports/{export}', [AccountPrivacyController::class, 'download'])
        ->middleware('signed')->name('account.privacy.exports.download');
    Route::get('/account/security', [AccountSecurityController::class, 'show'])
        ->middleware('sensitive.password-confirmed')
        ->name('account.security.show');
    Route::get('/account/sensitive-confirmation', [SensitiveTwoFactorConfirmationController::class, 'create'])
        ->middleware('sensitive.password-confirmed')
        ->name('account.sensitive-confirmation.create');
    Route::post('/account/sensitive-confirmation', [SensitiveTwoFactorConfirmationController::class, 'store'])
        ->middleware(['sensitive.password-confirmed', 'throttle:sensitive-two-factor'])
        ->name('account.sensitive-confirmation.store');
    Route::get('/account/favourites', [FavouriteController::class, 'index'])->name('account.favourites.index');
    Route::post('/favourites/{event}', [FavouriteController::class, 'store'])->name('favourites.store');
    Route::delete('/favourites/{event}', [FavouriteController::class, 'destroy'])->name('favourites.destroy');
    Route::get('/leader-hub', LeaderHubController::class)->name('leader-hub.index');
    Route::get('/leader-hub/documents/{document}/download', LeaderHubDocumentDownloadController::class)->name('leader-hub.documents.download');
    Route::get('/leader-hub/profile', [LeaderProfileSettingsController::class, 'edit'])->name('leader-hub.profile.edit');
    Route::patch('/leader-hub/profile', [LeaderProfileSettingsController::class, 'update'])->name('leader-hub.profile.update');
    Route::get('/leader-hub/walks/{walk}/duplicate', [LeaderHubDuplicateWalkController::class, 'edit'])->name('leader-hub.walks.duplicate.edit');
    Route::post('/leader-hub/walks/{walk}/duplicate', [LeaderHubDuplicateWalkController::class, 'store'])->name('leader-hub.walks.duplicate.store');
});
Route::get('/walks', PublicWalkIndexController::class)->name('walks.index');
Route::get('/walks/grading-guide', WalkGradingGuideController::class)->name('walks.grading-guide');
Route::get('/walks/{slug}/attachments/{attachment}', PublicWalkAttachmentDownloadController::class)->whereNumber('attachment')->name('walks.attachment');
Route::get('/walks/{slug}/route.gpx', PublicWalkGpxDownloadController::class)->name('walks.gpx');
Route::get('/walks/{slug}', PublicWalkShowController::class)->name('walks.show');
Route::get('/socials', PublicSocialIndexController::class)->name('socials.index');
Route::get('/socials/{slug}/attachments/{attachment}', PublicSocialAttachmentDownloadController::class)->whereNumber('attachment')->name('socials.attachment');
Route::get('/socials/{slug}', PublicSocialShowController::class)->name('socials.show');
Route::get('/weekends', PublicHolidayIndexController::class)->name('holidays.index');
Route::get('/weekends/{slug}/attachments/{attachment}', PublicHolidayAttachmentDownloadController::class)->whereNumber('attachment')->name('holidays.attachment');
Route::get('/weekends/{slug}', PublicHolidayShowController::class)->name('holidays.show');
Route::get('/whats-on', WhatsOnController::class)->name('events.index');
Route::get('/whats-on/calendar', WhatsOnCalendarController::class)->name('events.calendar');
Route::get('/calendar.ics', CalendarFeedController::class)->defaults('calendarType', 'all')->name('calendar.all');
Route::get('/calendar/{calendarType}.ics', CalendarFeedController::class)->whereIn('calendarType', ['walks', 'socials', 'holidays'])->name('calendar.type');

if (app()->environment(['local', 'testing', 'browser-testing'])) {
    Route::get('/_dev/components', function () {
        return view('dev.components', [
            'theme' => BrandTheme::fromSiteProfile(new SiteProfile),
            'site' => ['name' => 'Waymark Community'],
            'banner' => [
                'version' => 'component-story-v1',
                'message' => 'Booking is open for the next weekend away.',
                'action_label' => 'See the weekend',
                'action_url' => route('holidays.index'),
            ],
            'event' => [
                'title' => 'Ridge and reservoir',
                'url' => route('walks.show', 'ridge-and-reservoir'),
                'image_url' => '/images/demo/hero-walkers.png',
                'image_alt' => 'Walkers following a mountain path',
                'date' => 'Saturday 24 August',
                'day' => 'Sat',
                'day_number' => '24',
                'month' => 'Aug',
                'location' => 'North Moor',
                'distance' => '8.5 miles',
                'ascent' => '1,250 ft',
                'difficulty' => 'Moderate',
                'capacity' => '12 of 16',
                'leader' => 'James S.',
                'status' => 'Spaces available',
            ],
            'photo' => [
                'image_url' => '/images/demo/woodland-walk.png',
                'image_alt' => 'A green valley beneath a wide sky',
                'caption' => 'A bright day above the valley',
            ],
        ]);
    })->name('dev.components');
}
