<?php

namespace App\ViewModels;

use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Models\User;

final readonly class AccountSecurityPageViewModel
{
    /**
     * @param  array<string, string>  $site
     * @param  list<string>  $recoveryCodes
     */
    private function __construct(
        public array $site,
        public BrandTheme $theme,
        public bool $twoFactorEnabled,
        public bool $twoFactorSetupInProgress,
        public ?string $twoFactorQrCodeSvg,
        public array $recoveryCodes,
    ) {}

    public static function for(User $user): self
    {
        $siteProfile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;
        $twoFactorEnabled = $user->hasEnabledTwoFactorAuthentication();
        $twoFactorSetupInProgress = ! $twoFactorEnabled && filled($user->two_factor_secret);

        return new self(
            site: [
                'name' => $siteProfile->group_name ?? 'Waymark Community',
                'strapline' => 'A local walking community',
            ],
            theme: BrandTheme::fromSiteProfile($siteProfile),
            twoFactorEnabled: $twoFactorEnabled,
            twoFactorSetupInProgress: $twoFactorSetupInProgress,
            twoFactorQrCodeSvg: $twoFactorSetupInProgress ? $user->twoFactorQrCodeSvg() : null,
            recoveryCodes: $twoFactorEnabled ? $user->recoveryCodes() : [],
        );
    }

    /** @return array{site: array<string, string>, theme: BrandTheme, twoFactorEnabled: bool, twoFactorSetupInProgress: bool, twoFactorQrCodeSvg: ?string, recoveryCodes: list<string>} */
    public function toArray(): array
    {
        return [
            'site' => $this->site,
            'theme' => $this->theme,
            'twoFactorEnabled' => $this->twoFactorEnabled,
            'twoFactorSetupInProgress' => $this->twoFactorSetupInProgress,
            'twoFactorQrCodeSvg' => $this->twoFactorQrCodeSvg,
            'recoveryCodes' => $this->recoveryCodes,
        ];
    }
}
