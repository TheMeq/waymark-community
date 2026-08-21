<?php

namespace App\ViewModels;

use App\Domain\Events\Models\Event;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Filament\Resources\WalkResource;
use App\Models\User;
use Illuminate\Support\Collection;

final readonly class LeaderHubPageViewModel
{
    /**
     * @param  array<string, list<array<string, string|null>>>  $walks
     */
    private function __construct(
        public array $site,
        public BrandTheme $theme,
        public string $leaderName,
        public array $walks,
    ) {}

    /**
     * @param  Collection<int, Event>  $drafts
     * @param  Collection<int, Event>  $upcoming
     * @param  Collection<int, Event>  $past
     */
    public static function from(User $leader, Collection $drafts, Collection $upcoming, Collection $past): self
    {
        $siteProfile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;

        return new self(
            site: [
                'name' => $siteProfile->group_name ?? 'Waymark Community',
                'strapline' => 'A local walking community',
            ],
            theme: BrandTheme::fromSiteProfile($siteProfile),
            leaderName: $leader->publicDisplayName(),
            walks: [
                'drafts' => self::walks($drafts),
                'upcoming' => self::walks($upcoming),
                'past' => self::walks($past),
            ],
        );
    }

    /** @param Collection<int, Event> $events
     * @return list<array<string, string|null>>
     */
    private static function walks(Collection $events): array
    {
        return $events->map(function (Event $event): array {
            $walk = $event->walk;

            return [
                'title' => $event->title,
                'status' => str($event->status->value)->replace('_', ' ')->title()->toString(),
                'when' => $event->starts_at->format('j M Y, H:i'),
                'notes' => $walk?->private_organiser_notes,
                'edit_url' => WalkResource::getUrl('edit', ['record' => $walk]),
                'duplicate_url' => route('leader-hub.walks.duplicate.edit', $walk),
            ];
        })->all();
    }

    /** @return array{site: array<string, string>, theme: BrandTheme, leaderName: string, walks: array<string, list<array<string, string|null>>>} */
    public function toArray(): array
    {
        return [
            'site' => $this->site,
            'theme' => $this->theme,
            'leaderName' => $this->leaderName,
            'walks' => $this->walks,
        ];
    }
}
