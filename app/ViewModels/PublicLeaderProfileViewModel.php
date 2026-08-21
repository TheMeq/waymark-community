<?php

namespace App\ViewModels;

use App\Domain\Accounts\Data\LeaderProfilePhoto;
use App\Domain\Events\Models\Event;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Models\User;
use Illuminate\Support\Collection;

final readonly class PublicLeaderProfileViewModel
{
    /**
     * @param  Collection<int, Event>  $events
     * @param  list<array<string, string>>  $walks
     */
    private function __construct(
        public array $site,
        public BrandTheme $theme,
        public array $leader,
        public array $walks,
    ) {}

    /** @param Collection<int, Event> $events */
    public static function from(User $leader, Collection $events): self
    {
        $siteProfile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;
        $photo = LeaderProfilePhoto::resolve($leader->profile_photo_reference);

        return new self(
            site: [
                'name' => $siteProfile->group_name ?? 'Waymark Community',
                'strapline' => 'A local walking community',
            ],
            theme: BrandTheme::fromSiteProfile($siteProfile),
            leader: array_filter([
                'name' => $leader->publicDisplayName(),
                'introduction' => $leader->public_profile_introduction,
                'photo_url' => $photo?->url,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            walks: $events
                ->map(fn (Event $event): array => PublicWalkCardViewModel::fromEvent($event, $siteProfile))
                ->all(),
        );
    }

    /** @return array{site: array<string, string>, theme: BrandTheme, leader: array<string, string>, walks: list<array<string, string>>} */
    public function toArray(): array
    {
        return [
            'site' => $this->site,
            'theme' => $this->theme,
            'leader' => $this->leader,
            'walks' => $this->walks,
        ];
    }
}
