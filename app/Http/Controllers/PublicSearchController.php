<?php

namespace App\Http\Controllers;

use App\Domain\Content\Data\PublicSearchFilters;
use App\Domain\Content\Queries\PublicSiteSearch;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Http\Requests\PublicSearchRequest;
use Illuminate\View\View;

final class PublicSearchController
{
    public function __invoke(PublicSearchRequest $request, PublicSiteSearch $search): View
    {
        $filters = PublicSearchFilters::from($request->validated());
        $profile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;

        return view('search.index', [
            'filters' => $filters,
            'results' => $search->search($filters, max(1, (int) $request->integer('page', 1))),
            'site' => ['name' => $profile->group_name ?? 'Waymark Community', 'strapline' => 'A local walking community'],
            'theme' => BrandTheme::fromSiteProfile($profile),
        ]);
    }
}
