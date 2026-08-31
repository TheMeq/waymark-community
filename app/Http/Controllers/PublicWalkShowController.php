<?php

namespace App\Http\Controllers;

use App\Domain\Accounts\Queries\FavouriteablePublicEventsQuery;
use App\Domain\Content\Presentation\PublicSeo;
use App\Domain\Operations\Queries\CurrentSiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Domain\Walks\Queries\PublicWalksQuery;
use App\Domain\Walks\RelatedContent\RelatedWalks;
use App\ViewModels\FavouriteControlViewModel;
use App\ViewModels\PublicWalkCardViewModel;
use App\ViewModels\PublicWalkDetailViewModel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class PublicWalkShowController
{
    public function __invoke(string $slug, PublicWalksQuery $walks, RelatedWalks $relatedWalks, FavouriteablePublicEventsQuery $favourites, Request $request, CurrentSiteProfile $currentProfile): View
    {
        $siteProfile = $currentProfile->get();
        $event = $walks->published()->with('updates')->where('slug', $slug)->firstOrFail();

        $walk = PublicWalkDetailViewModel::fromEvent($event, $siteProfile);

        return view('walks.show', [
            'site' => [
                'name' => $siteProfile->group_name ?? 'Waymark Community',
                'strapline' => 'A local walking community',
            ],
            'theme' => BrandTheme::fromSiteProfile($siteProfile),
            'event' => $event,
            'walk' => $walk,
            'seo' => app(PublicSeo::class)->event($event, $siteProfile, $walk['featured_image']['url'] ?? null),
            'favourite' => FavouriteControlViewModel::for($event, $request->user(), $favourites),
            'relatedWalks' => $relatedWalks->for($event)
                ->map(fn ($related) => PublicWalkCardViewModel::fromEvent($related, $siteProfile)),
        ]);
    }
}
