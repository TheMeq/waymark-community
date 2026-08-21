<?php

namespace App\ViewModels;

use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Models\User;
use Illuminate\Support\Collection;

final readonly class FavouritesPageViewModel
{
    /**
     * @param  array<string, list<array{title: string, when: string, url: string, remove_url: string}>>  $favourites
     */
    private function __construct(public array $site, public BrandTheme $theme, public array $favourites, public bool $hasFavourites) {}

    /** @param Collection<int, Event> $events */
    public static function from(User $user, Collection $events): self
    {
        $siteProfile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;
        $favourites = [
            'Walks' => [],
            'Socials' => [],
            'Weekends away' => [],
        ];

        foreach ($events as $event) {
            $label = match ($event->type) {
                EventType::Walk => 'Walks',
                EventType::Social => 'Socials',
                EventType::Holiday => 'Weekends away',
            };
            $favourites[$label][] = [
                'title' => $event->title,
                'when' => $event->starts_at->format('D j M Y, H:i'),
                'url' => self::eventUrl($event),
                'remove_url' => route('favourites.destroy', $event),
            ];
        }

        return new self(
            site: [
                'name' => $siteProfile->group_name ?? 'Waymark Community',
                'strapline' => 'A local walking community',
            ],
            theme: BrandTheme::fromSiteProfile($siteProfile),
            favourites: $favourites,
            hasFavourites: $events->isNotEmpty(),
        );
    }

    /** @return array{site: array<string, string>, theme: BrandTheme, favourites: array<string, list<array{title: string, when: string, url: string, remove_url: string}>>, hasFavourites: bool} */
    public function toArray(): array
    {
        return [
            'site' => $this->site,
            'theme' => $this->theme,
            'favourites' => $this->favourites,
            'hasFavourites' => $this->hasFavourites,
        ];
    }

    private static function eventUrl(Event $event): string
    {
        return match ($event->type) {
            EventType::Walk => route('walks.show', $event->slug),
            EventType::Social => route('socials.show', $event->slug),
            EventType::Holiday => route('holidays.show', $event->slug),
        };
    }
}
