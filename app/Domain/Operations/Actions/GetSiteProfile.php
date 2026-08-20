<?php

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Exceptions\SiteProfileNotConfigured;
use App\Domain\Operations\Models\SiteProfile;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class GetSiteProfile
{
    public function handle(): SiteProfile
    {
        try {
            return SiteProfile::query()
                ->where('is_active', true)
                ->sole();
        } catch (ModelNotFoundException) {
            throw new SiteProfileNotConfigured();
        }
    }
}
