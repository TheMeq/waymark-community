<?php

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Models\BrandingConfigurationSnapshot;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandingConfigurationValidator;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class UpdateSiteProfile
{
    public function __construct(private BrandingConfigurationValidator $brandingValidator) {}

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
        'favicon_path',
        'hero_default_path',
        'primary_colour',
        'accent_colour',
        'typography_option',
        'social_links',
        'terminology',
        'affiliation_name',
        'affiliation_url',
        'module_configuration',
    ];

    /** @param array<string, mixed> $validated */
    public function handle(array $validated, ?User $actor = null): SiteProfile
    {
        $this->brandingValidator->validate($validated);

        return DB::transaction(function () use ($validated, $actor): SiteProfile {
            $profile = SiteProfile::query()
                ->lockForUpdate()
                ->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;

            $previous = $profile->getAttributes();
            $profile->fill(Arr::only($validated, self::UPDATABLE_ATTRIBUTES));
            $profile->save();

            if ($actor !== null) {
                BrandingConfigurationSnapshot::query()->create(['actor_id' => $actor->id, 'previous_values' => $previous, 'new_values' => $profile->getAttributes()]);
            }

            return $profile->refresh();
        });
    }
}
