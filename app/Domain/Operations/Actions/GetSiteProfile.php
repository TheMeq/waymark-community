<?php

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Exceptions\SiteProfileNotConfigured;
use App\Domain\Operations\Models\SiteProfile;

final class GetSiteProfile
{
    public function handle(): SiteProfile
    {
        return SiteProfile::query()->find(SiteProfile::SINGLETON_ID)
            ?? throw new SiteProfileNotConfigured;
    }
}
