<?php

use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

if (app()->environment(['local', 'testing'])) {
    Route::get('/_dev/components', function () {
        return view('dev.components', [
            'theme' => BrandTheme::fromSiteProfile(new SiteProfile),
            'site' => ['name' => 'Waymark Community'],
            'event' => [
                'title' => 'Ridge and reservoir',
                'url' => '/walks/ridge-and-reservoir',
                'image_url' => 'https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=1200&q=80',
                'image_alt' => 'Walkers following a mountain path',
                'date' => 'Saturday 24 August',
                'distance' => '8.5 miles',
                'ascent' => '1,250 ft',
                'difficulty' => 'Moderate',
            ],
            'photo' => [
                'image_url' => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1200&q=80',
                'image_alt' => 'A green valley beneath a wide sky',
                'caption' => 'A bright day above the valley',
            ],
        ]);
    })->name('dev.components');
}
