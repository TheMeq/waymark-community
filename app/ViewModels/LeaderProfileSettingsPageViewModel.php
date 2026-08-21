<?php

namespace App\ViewModels;

use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Models\User;

final readonly class LeaderProfileSettingsPageViewModel
{
    private function __construct(
        public array $site,
        public BrandTheme $theme,
        public array $profile,
    ) {}

    public static function for(User $leader): self
    {
        $siteProfile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;

        return new self(
            site: [
                'name' => $siteProfile->group_name ?? 'Waymark Community',
                'strapline' => 'A local walking community',
            ],
            theme: BrandTheme::fromSiteProfile($siteProfile),
            profile: [
                'is_public' => $leader->public_profile_enabled,
                'slug' => $leader->public_profile_slug,
                'introduction' => $leader->public_profile_introduction,
                'display_name' => $leader->publicDisplayName(),
            ],
        );
    }

    /** @return array{site: array<string, string>, theme: BrandTheme, profile: array<string, string|bool|null>} */
    public function toArray(): array
    {
        return [
            'site' => $this->site,
            'theme' => $this->theme,
            'profile' => $this->profile,
        ];
    }
}
