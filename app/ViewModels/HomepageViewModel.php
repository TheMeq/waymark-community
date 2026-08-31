<?php

namespace App\ViewModels;

use App\Domain\Content\Presentation\PublicUrl;
use App\Domain\Operations\Models\SiteProfile;

final readonly class HomepageViewModel
{
    /**
     * @param  array<string, string>  $site
     * @param  array<string, mixed>  $hero
     * @param  array<int, array<string, string>>  $benefits
     * @param  array<int, array<string, string>>  $weekendWalks
     * @param  array<string, string>  $holiday
     * @param  array<int, array<string, string>>  $gallery
     * @param  array<int, array<string, string>>  $memberResources
     */
    private function __construct(
        public array $site,
        public array $hero,
        public array $benefits,
        public array $weekendWalks,
        public array $holiday,
        public array $gallery,
        public array $memberResources,
        public string $testimonial,
    ) {}

    /** @param array<int, array<string, string>>|null $weekendWalks */
    public static function demo(?array $weekendWalks = null, ?array $holiday = null, ?array $gallery = null, ?string $testimonial = null): self
    {
        return new self(
            site: [
                'name' => 'Waymark Community',
                'strapline' => 'A local walking community',
            ],
            hero: [
                'eyebrow' => 'Walk  •  Explore  •  Connect',
                'headline' => 'Great walks. Good people.',
                'highlight' => 'Weekend adventures.',
                'summary' => 'A friendly walking group for adults. Explore local trails and trips further afield with good company.',
                'image_url' => PublicUrl::asset('/images/demo/hero-walkers-1536.webp'),
                'image_srcset' => PublicUrl::asset('/images/demo/hero-walkers-768.webp').' 768w, '.PublicUrl::asset('/images/demo/hero-walkers-1536.webp').' 1536w',
                'image_alt' => 'Friends walking together across open moorland',
                'focal_position' => '68% 32%',
            ],
            benefits: [
                ['symbol' => 'people', 'title' => 'Friendly community', 'detail' => 'Everyone welcome'],
                ['symbol' => 'route', 'title' => 'Scenic routes', 'detail' => 'A range of abilities'],
                ['symbol' => 'calendar', 'title' => 'Weekends away', 'detail' => 'Memories that last'],
            ],
            weekendWalks: $weekendWalks ?? [
                [
                    'title' => 'Ridge and reservoir',
                    'url' => route('walks.show', 'ridge-and-reservoir'),
                    'image_url' => PublicUrl::asset('/images/demo/hero-walkers-768.webp'),
                    'image_alt' => 'Walkers following an upland trail above green valleys',
                    'date' => 'Saturday 24 August',
                    'day' => 'Sat',
                    'day_number' => '24',
                    'month' => 'Aug',
                    'location' => 'North Moor',
                    'distance' => '8.5 miles',
                    'ascent' => '1,250 ft',
                    'difficulty' => 'Moderate',
                    'capacity' => '12 of 16',
                    'leader' => 'James S.',
                    'status' => 'Spaces available',
                ],
                [
                    'title' => 'Woodland and water',
                    'url' => route('walks.show', 'woodland-and-water'),
                    'image_url' => PublicUrl::asset('/images/demo/woodland-walk-768.webp'),
                    'image_alt' => 'Walkers crossing a footbridge through green woodland',
                    'date' => 'Sunday 25 August',
                    'day' => 'Sun',
                    'day_number' => '25',
                    'month' => 'Aug',
                    'location' => 'West Woods',
                    'distance' => '6 miles',
                    'ascent' => '720 ft',
                    'difficulty' => 'Leisurely',
                    'capacity' => '8 of 16',
                    'leader' => 'Rachael D.',
                    'status' => '8 spaces left',
                ],
                [
                    'title' => 'Moorland views',
                    'url' => route('walks.show', 'moorland-views'),
                    'image_url' => PublicUrl::asset('/images/demo/lakeside-friends-768.webp'),
                    'image_alt' => 'Friends pausing beside a quiet upland lake',
                    'date' => 'Monday 26 August',
                    'day' => 'Mon',
                    'day_number' => '26',
                    'month' => 'Aug',
                    'location' => 'High Moor',
                    'distance' => '10 miles',
                    'ascent' => '1,600 ft',
                    'difficulty' => 'Challenging',
                    'capacity' => '10 of 16',
                    'leader' => 'Tom B.',
                    'status' => '6 spaces left',
                ],
            ],
            holiday: $holiday ?? [
                'title' => 'Coast and moor long weekend',
                'url' => route('holidays.show', 'coast-and-moor'),
                'image_url' => PublicUrl::asset('/images/demo/coastal-weekend-768.webp'),
                'image_alt' => 'Walkers arriving at a stone lodge beside the coast',
                'duration' => '3 nights',
                'location' => 'Coast and moorland',
                'date' => '12–15 September',
                'summary' => 'Big skies, coastal paths and an easygoing base for the weekend.',
            ],
            gallery: $gallery ?? [
                ['image_url' => PublicUrl::asset('/images/demo/lakeside-friends-768.webp'), 'image_alt' => 'Friends sharing a warm drink beside an upland lake'],
                ['image_url' => PublicUrl::asset('/images/demo/woodland-walk-768.webp'), 'image_alt' => 'A footbridge winding through lush woodland'],
                ['image_url' => PublicUrl::asset('/images/demo/coastal-weekend-768.webp'), 'image_alt' => 'A walking weekend on a broad coastal headland'],
                ['image_url' => PublicUrl::asset('/images/demo/hero-walkers-768.webp'), 'image_alt' => 'A group walking across open moorland'],
                ['image_url' => PublicUrl::asset('/images/demo/woodland-walk-768.webp'), 'image_alt' => 'Sunlight falling through ferns beside a woodland trail'],
                ['image_url' => PublicUrl::asset('/images/demo/lakeside-friends-768.webp'), 'image_alt' => 'Walkers laughing together after a day outside'],
            ],
            memberResources: [
                ['label' => 'Members area', 'url' => route('account.profile.edit')],
                ['label' => 'Policies and documents', 'url' => route('documents.index')],
                ['label' => 'Walk leader resources', 'url' => route('leader-hub.index')],
                ['label' => 'Contacts', 'url' => route('contact.create')],
            ],
            testimonial: $testimonial ?? '“I came along for one walk and found a whole community.”',
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $weekendWalks
     * @param  array<string, mixed>|null  $holiday
     * @param  array<int, array<string, mixed>>  $gallery
     * @param  array<string, string>  $heroOverrides
     */
    public static function live(SiteProfile $profile, array $weekendWalks, ?array $holiday, array $gallery, ?string $testimonial, array $heroOverrides = []): self
    {
        $defaults = self::demo($weekendWalks, $holiday ?? [], $gallery, $testimonial);

        $hero = array_replace($defaults->hero, $heroOverrides);
        if (($hero['image_url'] ?? null) !== PublicUrl::asset('/images/demo/hero-walkers-1536.webp')) {
            unset($hero['image_srcset']);
        }

        return new self(
            site: ['name' => $profile->group_name ?: 'Waymark Community', 'strapline' => 'A local walking community'],
            hero: $hero,
            benefits: $defaults->benefits,
            weekendWalks: $weekendWalks,
            holiday: $holiday ?? [],
            gallery: $gallery,
            memberResources: [
                ['label' => 'Members area', 'url' => route('account.profile.edit')],
                ['label' => 'Policies and documents', 'url' => route('documents.index')],
                ['label' => 'Walk leader resources', 'url' => route('leader-hub.index')],
                ['label' => 'Contacts', 'url' => route('contact.create')],
            ],
            testimonial: $testimonial ?? '',
        );
    }
}
