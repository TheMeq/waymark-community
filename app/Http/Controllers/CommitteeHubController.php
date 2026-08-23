<?php

namespace App\Http\Controllers;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Governance\Queries\CommitteeHubContent;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CommitteeHubController
{
    public function __invoke(Request $request, CommitteeHubContent $content): View
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->hasCapability(ModuleCapability::AccessCommitteeHub), 403);
        $profile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;

        return view('committee.hub', [...$content->get($actor), 'site' => ['name' => $profile->group_name ?? 'Waymark Community', 'strapline' => 'A local walking community'], 'theme' => BrandTheme::fromSiteProfile($profile)]);
    }
}
