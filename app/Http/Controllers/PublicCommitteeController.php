<?php

namespace App\Http\Controllers;

use App\Domain\Governance\Queries\PublicCommittee;
use App\Domain\Governance\Queries\PublicCommitteeMeetings;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Domain\SiteMedia\ContentImagePresenter;
use App\Domain\SiteMedia\Enums\ContentImageSlot;
use Illuminate\View\View;

final class PublicCommitteeController
{
    public function index(PublicCommittee $committee): View
    {
        $roles = $committee->get();

        return view('committee.index', [...$this->site(), 'roles' => $roles,
            'roleImages' => $roles->mapWithKeys(fn ($role): array => [$role->id => app(ContentImagePresenter::class)->present($role, ContentImageSlot::Committee)]),
        ]);
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
