<?php

namespace App\ViewModels;

use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Domain\Walks\Enums\DuplicateWalkCopyGroup;
use App\Domain\Walks\Models\Walk;

final readonly class LeaderHubDuplicateWalkPageViewModel
{
    /**
     * @param  array<string, string>  $copyGroups
     * @param  list<string>  $defaults
     */
    private function __construct(
        public array $site,
        public BrandTheme $theme,
        public Walk $source,
        public array $copyGroups,
        public array $defaults,
    ) {}

    public static function from(Walk $source): self
    {
        $siteProfile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;

        return new self(
            site: [
                'name' => $siteProfile->group_name ?? 'Waymark Community',
                'strapline' => 'A local walking community',
            ],
            theme: BrandTheme::fromSiteProfile($siteProfile),
            source: $source,
            copyGroups: DuplicateWalkCopyGroup::options(),
            defaults: DuplicateWalkCopyGroup::defaults(),
        );
    }

    /** @return array{site: array<string, string>, theme: BrandTheme, source: Walk, copyGroups: array<string, string>, defaults: list<string>} */
    public function toArray(): array
    {
        return [
            'site' => $this->site,
            'theme' => $this->theme,
            'source' => $this->source,
            'copyGroups' => $this->copyGroups,
            'defaults' => $this->defaults,
        ];
    }
}
