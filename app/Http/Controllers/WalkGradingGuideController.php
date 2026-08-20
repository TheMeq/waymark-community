<?php

namespace App\Http\Controllers;

use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Domain\Walks\Models\Grade;
use Illuminate\Contracts\View\View;

final class WalkGradingGuideController
{
    public function __invoke(): View
    {
        $siteProfile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;

        return view('walks.grading-guide', [
            'site' => [
                'name' => $siteProfile->group_name ?? 'Waymark Community',
                'strapline' => 'A local walking community',
            ],
            'theme' => BrandTheme::fromSiteProfile($siteProfile),
            'grades' => Grade::query()->orderBy('display_order')->orderBy('name')->get(),
        ]);
    }
}
