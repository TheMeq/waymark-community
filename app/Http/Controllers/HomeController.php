<?php

namespace App\Http\Controllers;

use App\Domain\Holidays\Queries\PublicHolidaysQuery;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Domain\Walks\Queries\PublicWalksQuery;
use App\ViewModels\HomepageViewModel;
use App\ViewModels\PublicHolidayCardViewModel;
use App\ViewModels\PublicWalkCardViewModel;
use Illuminate\Contracts\View\View;

final class HomeController
{
    public function __invoke(PublicWalksQuery $walks, PublicHolidaysQuery $holidays): View
    {
        $siteProfile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;
        $weekendWalks = $walks->weekend()
            ->limit(3)
            ->get()
            ->map(fn ($event) => PublicWalkCardViewModel::fromEvent($event, $siteProfile))
            ->all();

        $holiday = $holidays->upcoming()->first();

        return view('home', [
            'homepage' => HomepageViewModel::demo($weekendWalks, $holiday === null ? null : PublicHolidayCardViewModel::spotlight($holiday)),
            'theme' => BrandTheme::fromSiteProfile($siteProfile),
        ]);
    }
}
