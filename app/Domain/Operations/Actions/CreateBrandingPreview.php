<?php

namespace App\Domain\Operations\Actions;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Operations\Data\BrandingImageInput;
use App\Domain\Operations\Data\CreatedBrandingPreview;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandingConfigurationValidator;
use App\Domain\SiteMedia\Enums\ManagedImageSource;
use App\Domain\SiteMedia\Enums\SiteMediaPurpose;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateBrandingPreview
{
    public function __construct(private BrandingConfigurationValidator $validator) {}

    /** @param array<string, mixed> $values */
    public function handle(User $actor, array $values): CreatedBrandingPreview
    {
        if (! $actor->hasCapability(ModuleCapability::ManageContent)) {
            throw ValidationException::withMessages(['branding' => 'You are not allowed to preview branding.']);
        }

        $profile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;
        $submittedValues = $values;
        $ordinaryValues = $submittedValues;

        foreach (['logo', 'favicon'] as $slot) {
            if (array_key_exists($slot.'_source', $values)) {
                unset(
                    $ordinaryValues[$slot.'_source'],
                    $ordinaryValues[$slot.'_upload'],
                    $ordinaryValues[$slot.'_path'],
                    $ordinaryValues[$slot.'_remove_fallback'],
                );
            }
        }

        $values = array_replace(
            $profile->only([
                'group_name',
                'short_name',
                'contact_email',
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
            ]),
            $this->validator->validate($ordinaryValues),
            [
                'logo_media_id' => $profile->logo_media_id,
                'favicon_media_id' => $profile->favicon_media_id,
            ],
        );

        foreach ([
            'logo' => SiteMediaPurpose::SiteLogo,
            'favicon' => SiteMediaPurpose::SiteFavicon,
        ] as $slot => $purpose) {
            if (array_key_exists($slot.'_source', $submittedValues)) {
                $input = BrandingImageInput::from($submittedValues, $purpose);
                $values[$slot.'_media_id'] = $input->source === ManagedImageSource::Managed
                    ? $profile->{$slot.'_media_id'}
                    : null;

                if ($input->source === ManagedImageSource::External) {
                    $values[$slot.'_path'] = $input->externalUrl;
                } elseif ($input->source === ManagedImageSource::None && $input->removeFallback) {
                    $values[$slot.'_path'] = null;
                }
            }
        }
        $token = Str::random(48);
        Cache::store('file')->put('branding-preview:'.$token, ['user_id' => $actor->id, 'values' => $values], now()->addMinutes(30));

        return new CreatedBrandingPreview($token);
    }
}
