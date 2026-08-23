<?php

namespace App\ViewModels;

final readonly class HomepageViewModel
{
    /**
     * @param  array<string, string>  $site
     * @param  array<string, string>  $hero
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
                'image_url' => '/images/demo/hero-walkers.png',
                'image_alt' => 'Friends walking together across open moorland',
            ],
            benefits: [
                ['symbol' => 'people', 'title' => 'Friendly community', 'detail' => 'Everyone welcome'],
                ['symbol' => 'route', 'title' => 'Scenic routes', 'detail' => 'A range of abilities'],
                ['symbol' => 'calendar', 'title' => 'Weekends away', 'detail' => 'Memories that last'],
            ],
            weekendWalks: $weekendWalks ?? [
                [
                    'title' => 'Ridge and reservoir',
                    'url' => '/walks/ridge-and-reservoir',
                    'image_url' => '/images/demo/hero-walkers.png',
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
                    'url' => '/walks/woodland-and-water',
                    'image_url' => '/images/demo/woodland-walk.png',
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
                    'url' => '/walks/moorland-views',
                    'image_url' => '/images/demo/lakeside-friends.png',
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
                'url' => '/weekends/coast-and-moor',
                'image_url' => '/images/demo/coastal-weekend.png',
                'image_alt' => 'Walkers arriving at a stone lodge beside the coast',
                'duration' => '3 nights',
                'location' => 'Coast and moorland',
                'date' => '12–15 September',
                'summary' => 'Big skies, coastal paths and an easygoing base for the weekend.',
            ],
            gallery: $gallery ?? [
                ['image_url' => '/images/demo/lakeside-friends.png', 'image_alt' => 'Friends sharing a warm drink beside an upland lake'],
                ['image_url' => '/images/demo/woodland-walk.png', 'image_alt' => 'A footbridge winding through lush woodland'],
                ['image_url' => '/images/demo/coastal-weekend.png', 'image_alt' => 'A walking weekend on a broad coastal headland'],
                ['image_url' => '/images/demo/hero-walkers.png', 'image_alt' => 'A group walking across open moorland'],
                ['image_url' => '/images/demo/woodland-walk.png', 'image_alt' => 'Sunlight falling through ferns beside a woodland trail'],
                ['image_url' => '/images/demo/lakeside-friends.png', 'image_alt' => 'Walkers laughing together after a day outside'],
            ],
            memberResources: [
                ['label' => 'Members area', 'url' => '/account'],
                ['label' => 'Policies and documents', 'url' => '/documents'],
                ['label' => 'Walk leader resources', 'url' => '/walk-leaders'],
                ['label' => 'Contacts', 'url' => '/contact'],
            ],
            testimonial: $testimonial ?? '“I came along for one walk and found a whole community.”',
        );
    }
}
