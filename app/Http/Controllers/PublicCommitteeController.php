<?php

namespace App\Http\Controllers;

use App\Domain\Governance\Queries\PublicCommittee;
use App\Domain\Governance\Queries\PublicCommitteeMeetings;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use Illuminate\View\View;

final class PublicCommitteeController
{
    public function index(PublicCommittee $committee): View
    {
        return view('committee.index', [...$this->site(), 'roles' => $committee->get()]);
    }

    public function meetings(PublicCommitteeMeetings $meetings): View
    {
        return view('committee.meetings', [...$this->site(), 'meetings' => $meetings->get()]);
    }

    private function site(): array
    {
        $profile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;

        return ['site' => ['name' => $profile->group_name ?? 'Waymark Community', 'strapline' => 'A local walking community'], 'theme' => BrandTheme::fromSiteProfile($profile)];
    }
}
