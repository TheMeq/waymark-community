<?php

namespace App\ViewModels;

use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;

final readonly class PublicAccountPageViewModel
{
    /** @param array<string, string> $site */
    private function __construct(
        public array $site,
        public BrandTheme $theme,
    ) {}

    public static function current(): self
    {
        $siteProfile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;

        return new self(
            site: [
                'name' => $siteProfile->group_name ?? 'Waymark Community',
                'strapline' => 'A local walking community',
            ],
            theme: BrandTheme::fromSiteProfile($siteProfile),
        );
    }
}
