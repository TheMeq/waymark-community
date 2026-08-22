<?php

namespace App\Http\Controllers;

use App\Domain\Accounts\Queries\FavouriteablePublicEventsQuery;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Gallery\Queries\HolidayCommunityPhotos;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\ViewModels\FavouriteControlViewModel;
use App\ViewModels\PublicHolidayDetailViewModel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class PublicHolidayShowController
{
    public function __invoke(string $slug, FavouriteablePublicEventsQuery $favourites, HolidayCommunityPhotos $photos, Request $request): View
    {
        $event = Event::query()->with(['holiday', 'organiser', 'children'])
            ->where('slug', $slug)->where('type', EventType::Holiday)
            ->whereIn('status', [EventStatus::Published, EventStatus::Changed, EventStatus::Postponed, EventStatus::Cancelled])
            ->where('is_public', true)->whereNotNull('published_at')->where('published_at', '<=', now())->firstOrFail();

        $siteProfile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;

        return view('holidays.show', [
            'event' => $event,
            'holiday' => PublicHolidayDetailViewModel::fromEvent($event),
            'gallery' => $photos->forHoliday($event, perPage: 6),
            'favourite' => FavouriteControlViewModel::for($event, $request->user(), $favourites),
            'site' => ['name' => $siteProfile->group_name ?? 'Waymark Community', 'strapline' => 'A local walking community'],
            'theme' => BrandTheme::fromSiteProfile($siteProfile),
        ]);
    }
}
