<?php

namespace App\ViewModels;

use App\Domain\Events\Models\Event;
use App\Domain\Governance\Models\Document;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Domain\Walks\Models\Walk;
use App\Filament\Resources\WalkResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final readonly class LeaderHubPageViewModel
{
    /**
     * @param  array<string, list<array<string, string|null>>>  $walks
     */
    private function __construct(
        public array $site,
        public BrandTheme $theme,
        public string $leaderName,
        public ?string $createWalkUrl,
        public array $walks,
        public array $resources,
    ) {}

    /**
     * @param  Collection<int, Event>  $drafts
     * @param  Collection<int, Event>  $upcoming
     * @param  Collection<int, Event>  $past
     */
    public static function from(User $leader, Collection $drafts, Collection $upcoming, Collection $past, Collection $documents): self
    {
        $siteProfile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;
        $canAccessAdministration = $leader->canAccessPanel(Filament::getPanel('admin'));

        return new self(
            site: [
                'name' => $siteProfile->group_name ?? 'Waymark Community',
                'strapline' => 'A local walking community',
            ],
            theme: BrandTheme::fromSiteProfile($siteProfile),
            leaderName: $leader->publicDisplayName(),
            createWalkUrl: $canAccessAdministration && Gate::forUser($leader)->allows('create', Walk::class)
                ? WalkResource::getUrl('create')
                : null,
            walks: [
                'drafts' => self::walks($drafts, $leader, $canAccessAdministration),
                'upcoming' => self::walks($upcoming, $leader, $canAccessAdministration),
                'past' => self::walks($past, $leader, $canAccessAdministration),
            ],
            resources: $documents->map(fn (Document $document): array => [
                'title' => $document->title,
                'description' => $document->description,
                'category' => $document->category?->name,
                'download_url' => route('leader-hub.documents.download', $document),
            ])->all(),
        );
    }

    /** @param Collection<int, Event> $events
     * @return list<array<string, string|null>>
     */
    private static function walks(Collection $events, User $leader, bool $canAccessAdministration): array
    {
        return $events->map(function (Event $event) use ($leader, $canAccessAdministration): array {
            $walk = $event->walk;

            return [
                'title' => $event->title,
                'status' => str($event->status->value)->replace('_', ' ')->title()->toString(),
                'when' => $event->starts_at->format('j M Y, H:i'),
                'notes' => $walk?->private_organiser_notes,
                'edit_url' => $canAccessAdministration && $walk instanceof Walk && Gate::forUser($leader)->allows('update', $walk)
                    ? WalkResource::getUrl('edit', ['record' => $walk])
                    : null,
                'duplicate_url' => route('leader-hub.walks.duplicate.edit', $walk),
            ];
        })->all();
    }

    /** @return array{site: array<string, string>, theme: BrandTheme, leaderName: string, createWalkUrl: ?string, walks: array<string, list<array<string, string|null>>>, resources: list<array<string, string|null>>} */
    public function toArray(): array
    {
        return [
            'site' => $this->site,
            'theme' => $this->theme,
            'leaderName' => $this->leaderName,
            'createWalkUrl' => $this->createWalkUrl,
            'walks' => $this->walks,
            'resources' => $this->resources,
        ];
    }
}
