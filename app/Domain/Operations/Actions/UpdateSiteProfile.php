<?php

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Models\SiteProfile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class UpdateSiteProfile
{
    private const UPDATABLE_ATTRIBUTES = [
        'group_name',
        'short_name',
        'contact_email',
        'timezone',
        'locale',
        'distance_unit',
        'ascent_unit',
        'start_year',
        'logo_path',
        'primary_colour',
        'accent_colour',
        'affiliation_name',
        'affiliation_url',
        'module_configuration',
    ];

    /** @param array<string, mixed> $validated */
    public function handle(array $validated): SiteProfile
    {
        return DB::transaction(function () use ($validated): SiteProfile {
            $profile = SiteProfile::query()
                ->lockForUpdate()
                ->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;

            $profile->fill(Arr::only($validated, self::UPDATABLE_ATTRIBUTES));
            $profile->save();

            return $profile->refresh();
        });
    }
}
