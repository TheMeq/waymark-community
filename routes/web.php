<?php

use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

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
