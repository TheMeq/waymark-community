<?php

use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Http\Controllers\AccountProfileController;
use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\FavouriteController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LeaderHubController;
use App\Http\Controllers\LeaderHubDuplicateWalkController;
use App\Http\Controllers\LeaderProfileSettingsController;
use App\Http\Controllers\NewHereController;
use App\Http\Controllers\PublicHolidayAttachmentDownloadController;
use App\Http\Controllers\PublicHolidayIndexController;
use App\Http\Controllers\PublicHolidayShowController;
use App\Http\Controllers\PublicLeaderProfileController;
use App\Http\Controllers\PublicSocialAttachmentDownloadController;
use App\Http\Controllers\PublicSocialIndexController;
use App\Http\Controllers\PublicSocialShowController;
use App\Http\Controllers\PublicWalkAttachmentDownloadController;
use App\Http\Controllers\PublicWalkGpxDownloadController;
use App\Http\Controllers\PublicWalkIndexController;
use App\Http\Controllers\PublicWalkShowController;
use App\Http\Controllers\WalkGradingGuideController;
use App\Http\Controllers\WhatsOnCalendarController;
use App\Http\Controllers\WhatsOnController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/new-here', NewHereController::class)->name('new-here');
Route::get('/leaders/{slug}', PublicLeaderProfileController::class)->name('leaders.show');
Route::middleware('auth')->group(function (): void {
    Route::get('/account/profile', [AccountProfileController::class, 'edit'])->name('account.profile.edit');
    Route::patch('/account/profile', [AccountProfileController::class, 'update'])->name('account.profile.update');
    Route::get('/account/favourites', [FavouriteController::class, 'index'])->name('account.favourites.index');
    Route::post('/favourites/{event}', [FavouriteController::class, 'store'])->name('favourites.store');
    Route::delete('/favourites/{event}', [FavouriteController::class, 'destroy'])->name('favourites.destroy');
    Route::get('/leader-hub', LeaderHubController::class)->name('leader-hub.index');
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

if (app()->environment(['local', 'testing'])) {
    Route::get('/_dev/components', function () {
        return view('dev.components', [
            'theme' => BrandTheme::fromSiteProfile(new SiteProfile),
            'site' => ['name' => 'Waymark Community'],
            'banner' => [
                'version' => 'component-story-v1',
                'message' => 'Booking is open for the next weekend away.',
                'action_label' => 'See the weekend',
                'action_url' => '/weekends',
            ],
            'event' => [
                'title' => 'Ridge and reservoir',
                'url' => '/walks/ridge-and-reservoir',
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
