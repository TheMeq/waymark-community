<?php

namespace App\Http\Controllers;

use App\Domain\Communication\Models\PolicyPage;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use Illuminate\View\View;

final class PublicPolicyController
{
    public function __invoke(string $slug): View
    {
        $page = PolicyPage::query()->with('currentVersion')->where('slug', $slug)->whereNotNull('current_version_id')->firstOrFail();
        abort_unless($page->currentVersion?->publication_state === 'published', 404);
        $profile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;

        return view('policies.show', ['policy' => $page, 'site' => ['name' => $profile->group_name ?? 'Waymark Community', 'strapline' => 'A local walking community'], 'theme' => BrandTheme::fromSiteProfile($profile)]);
    }
}
