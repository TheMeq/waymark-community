<?php

namespace App\Http\Controllers;

use App\Domain\Holidays\Queries\PublicHolidaysQuery;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\ViewModels\PublicHolidayCardViewModel;
use Illuminate\Contracts\View\View;

final class PublicHolidayIndexController
{
    public function __invoke(PublicHolidaysQuery $holidays): View
    {
        $siteProfile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;

        return view('holidays.index', [
            'site' => ['name' => $siteProfile->group_name ?? 'Waymark Community', 'strapline' => 'A local walking community'],
            'theme' => BrandTheme::fromSiteProfile($siteProfile),
            'holidays' => $holidays->upcoming()->paginate(12)->through(fn ($event) => PublicHolidayCardViewModel::fromEvent($event)),
        ]);
    }
}
