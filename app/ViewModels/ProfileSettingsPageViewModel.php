<?php

namespace App\ViewModels;

use App\Domain\Accounts\Models\CommunicationPreference;
use App\Domain\Accounts\Queries\ProfilePhotoPresentation;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Models\User;

final readonly class ProfileSettingsPageViewModel
{
    /**
     * @param  array<string, string>  $site
     * @param  array{name: string, email: string, display_name: ?string, phone: ?string, public_display_name: string, profile_photo_reference: ?string, photo: array{source: string, url: ?string, external_url: ?string}, can_upload_photo: bool}  $account
     * @param  list<array{key: string, label: string, description: string, is_subscribed: bool}>  $preferences
     */
    private function __construct(
        public array $site,
        public BrandTheme $theme,
        public array $account,
        public array $preferences,
    ) {}

    public static function for(User $user): self
    {
        $siteProfile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;
        $savedPreferences = $user->communicationPreferences()
            ->get()
            ->keyBy('category');

        $preferences = collect(CommunicationPreference::CATEGORIES)
            ->map(fn (array $definition, string $key): array => [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'is_subscribed' => $savedPreferences->get($key)?->is_subscribed ?? false,
            ])
            ->values()
            ->all();

        return new self(
            site: [
                'name' => $siteProfile->group_name ?? 'Waymark Community',
                'strapline' => 'A local walking community',
            ],
            theme: BrandTheme::fromSiteProfile($siteProfile),
            account: [
                'name' => $user->name,
                'email' => $user->email,
                'display_name' => $user->display_name,
                'phone' => $user->phone,
                'public_display_name' => $user->publicDisplayName(),
                'profile_photo_reference' => $user->profile_photo_reference,
                'photo' => app(ProfilePhotoPresentation::class)->for($user),
                'can_upload_photo' => $user->isActive() && $user->hasVerifiedEmail(),
            ],
            preferences: $preferences,
        );
    }

    /** @return array{site: array<string, string>, theme: BrandTheme, account: array{name: string, email: string, display_name: ?string, phone: ?string, public_display_name: string, profile_photo_reference: ?string, photo: array{source: string, url: ?string, external_url: ?string}, can_upload_photo: bool}, preferences: list<array{key: string, label: string, description: string, is_subscribed: bool}>} */
    public function toArray(): array
    {
        return [
            'site' => $this->site,
            'theme' => $this->theme,
            'account' => $this->account,
            'preferences' => $this->preferences,
        ];
    }
}
