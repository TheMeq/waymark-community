<?php

namespace App\Domain\Operations\Queries;

use App\Domain\Operations\Models\SiteProfile;
use Illuminate\Support\Facades\Schema;

final class CurrentSiteProfile
{
    private ?SiteProfile $profile = null;

    public function get(): SiteProfile
    {
        return $this->profile ??= Schema::hasTable('site_profiles')
            ? (SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile)
            : new SiteProfile;
    }
}
