<?php

namespace App\Http\Controllers;

use App\Domain\Accounts\Queries\FavouriteablePublicEventsQuery;
use App\Domain\Content\Presentation\PublicSeo;
use App\Domain\Gallery\Queries\HolidayCommunityPhotos;
use App\Domain\Holidays\Queries\PublicHolidaysQuery;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\ViewModels\FavouriteControlViewModel;
use App\ViewModels\PublicHolidayDetailViewModel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class PublicHolidayShowController
{
    public function __invoke(string $slug, FavouriteablePublicEventsQuery $favourites, HolidayCommunityPhotos $photos, PublicHolidaysQuery $holidays, Request $request): View
    {
        $event = $holidays->published()->with('children')->where('slug', $slug)->firstOrFail();

        $siteProfile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;

        return view('holidays.show', [
            'event' => $event,
            'holiday' => PublicHolidayDetailViewModel::fromEvent($event),
            'gallery' => $photos->forHoliday($event, perPage: 6),
            'favourite' => FavouriteControlViewModel::for($event, $request->user(), $favourites),
            'site' => ['name' => $siteProfile->group_name ?? 'Waymark Community', 'strapline' => 'A local walking community'],
            'theme' => BrandTheme::fromSiteProfile($siteProfile),
            'seo' => app(PublicSeo::class)->event($event, $siteProfile),
        ]);
    }
}
