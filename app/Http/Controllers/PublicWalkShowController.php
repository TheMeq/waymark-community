<?php

namespace App\Http\Controllers;

use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Domain\Walks\Queries\PublicWalksQuery;
use App\ViewModels\PublicWalkDetailViewModel;
use Illuminate\Contracts\View\View;

final class PublicWalkShowController
{
    public function __invoke(string $slug, PublicWalksQuery $walks): View
    {
        $siteProfile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;
        $event = $walks->published()->where('slug', $slug)->firstOrFail();

        return view('walks.show', [
            'site' => [
                'name' => $siteProfile->group_name ?? 'Waymark Community',
                'strapline' => 'A local walking community',
            ],
            'theme' => BrandTheme::fromSiteProfile($siteProfile),
            'event' => $event,
            'walk' => PublicWalkDetailViewModel::fromEvent($event, $siteProfile),
        ]);
    }
}
