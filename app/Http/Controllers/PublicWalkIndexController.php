<?php

namespace App\Http\Controllers;

use App\Domain\Operations\Queries\CurrentSiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Domain\Walks\Data\PublicWalkFilters;
use App\Domain\Walks\Models\Grade;
use App\Domain\Walks\Models\Tag;
use App\Domain\Walks\Queries\PublicWalksQuery;
use App\ViewModels\PublicWalkCardViewModel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class PublicWalkIndexController
{
    public function __invoke(Request $request, PublicWalksQuery $walks, CurrentSiteProfile $currentProfile): View
    {
        $siteProfile = $currentProfile->get();
        $filters = PublicWalkFilters::fromRequest($request);

        return view('walks.index', [
            'site' => [
                'name' => $siteProfile->group_name ?? 'Waymark Community',
                'strapline' => 'A local walking community',
            ],
            'theme' => BrandTheme::fromSiteProfile($siteProfile),
            'filters' => $filters,
            'grades' => Grade::query()->orderBy('display_order')->orderBy('name')->get(),
            'tags' => Tag::query()->orderBy('name')->get(),
            'leaders' => $walks->upcomingLeaders(),
            'walks' => $walks->filteredUpcoming($filters)
                ->paginate(9)
                ->withQueryString()
                ->through(fn ($event) => PublicWalkCardViewModel::fromEvent($event, $siteProfile)),
        ]);
    }
}
