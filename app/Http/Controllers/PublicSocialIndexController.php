<?php

namespace App\Http\Controllers;

use App\Domain\Events\Models\Event;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Domain\Socials\Queries\PublicSocialsQuery;
use App\ViewModels\PublicSocialCardViewModel;
use Illuminate\Contracts\View\View;

final class PublicSocialIndexController
{
    public function __invoke(PublicSocialsQuery $socials): View
    {
        $siteProfile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;

        return view('socials.index', [
            'site' => ['name' => $siteProfile->group_name ?? 'Waymark Community', 'strapline' => 'A local walking community'],
            'theme' => BrandTheme::fromSiteProfile($siteProfile),
            'socials' => $socials->upcoming()->paginate(9)->through(fn (Event $event) => PublicSocialCardViewModel::fromEvent($event)),
        ]);
    }
}
