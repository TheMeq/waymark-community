<?php

namespace App\Http\Controllers;

use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Domain\Socials\Queries\PublicSocialsQuery;
use App\ViewModels\FavouriteControlViewModel;
use App\ViewModels\PublicSocialDetailViewModel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class PublicSocialShowController
{
    public function __invoke(string $slug, PublicSocialsQuery $socials, Request $request): View
    {
        $siteProfile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;
        $event = $socials->published()->where('slug', $slug)->firstOrFail();

        return view('socials.show', [
            'site' => ['name' => $siteProfile->group_name ?? 'Waymark Community', 'strapline' => 'A local walking community'],
            'theme' => BrandTheme::fromSiteProfile($siteProfile),
            'event' => $event,
            'social' => PublicSocialDetailViewModel::fromEvent($event),
            'favourite' => FavouriteControlViewModel::for($event, $request->user()),
        ]);
    }
}
