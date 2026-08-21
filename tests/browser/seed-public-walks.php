<?php

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Holidays\Actions\AssignHolidayChild;
use App\Domain\Holidays\Actions\SaveHolidayDetails;
use App\Domain\Operations\Actions\UpdateSiteProfile;
use App\Domain\Socials\Actions\SaveSocialDetails;
use App\Domain\Walks\Actions\SaveWalkDetails;
use App\Domain\Walks\Models\Grade;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

app(UpdateSiteProfile::class)->handle(['group_name' => 'Example Walkers']);
$grade = Grade::query()->create([
    'display_order' => 10,
    'name' => 'Moderate',
    'description' => 'A steady pace over mixed terrain.',
]);
$leader = User::factory()->create(['name' => 'Morgan Walker']);

foreach ([
    ['Ridge and reservoir', 'hero-walkers.png', 8.5, 250, '2026-08-22 09:30:00'],
    ['Woodland and water', 'woodland-walk.png', 6.0, 180, '2026-08-23 10:30:00'],
    ['Moorland views', 'lakeside-friends.png', 10.0, 430, '2026-08-29 11:30:00'],
] as [$title, $image, $distance, $ascent, $start]) {
    $startsAt = CarbonImmutable::parse($start);
    $event = Event::query()->create([
        'type' => EventType::Walk,
        'title' => $title,
        'slug' => str($title)->slug(),
        'summary' => 'A friendly day out with practical details for walkers.',
        'description' => 'Please read the route and meeting information before travelling.',
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->copy()->addHours(5),
        'status' => EventStatus::Published,
        'is_public' => true,
        'published_at' => CarbonImmutable::parse('2026-08-19 12:00:00'),
        'organiser_id' => $leader->id,
    ]);

    app(SaveWalkDetails::class)->handle($event, [
        'primary_leader_id' => $leader->id,
        'grade_id' => $grade->id,
        'distance' => $distance,
        'ascent' => $ascent,
        'meeting_location_name' => 'Example meeting point',
        'terrain_notes' => 'Mixed paths with uneven ground in places.',
        'availability' => 'Places available',
        'featured_image_path' => '/images/demo/'.$image,
    ]);
}

$createEvent = function (EventType $type, string $title, string $slug, string $start, string $end, string $summary) use ($leader): Event {
    return Event::query()->create([
        'type' => $type,
        'title' => $title,
        'slug' => $slug,
        'summary' => $summary,
        'description' => 'Full practical information is provided here for independent public review.',
        'starts_at' => CarbonImmutable::parse($start),
        'ends_at' => CarbonImmutable::parse($end),
        'status' => EventStatus::Published,
        'is_public' => true,
        'published_at' => CarbonImmutable::parse('2026-08-19 12:00:00'),
        'organiser_id' => $leader->id,
    ]);
};

$social = $createEvent(
    EventType::Social,
    'Summer evening supper',
    'summer-evening-supper',
    '2026-08-26 19:00:00',
    '2026-08-26 22:00:00',
    'Good food and an easygoing evening with the group.',
);
app(SaveSocialDetails::class)->handle($social, [
    'venue_name' => 'Market Hall',
    'venue_address' => '1 Market Square',
    'cost' => '£8 per person',
    'booking_status' => 'Booking open',
    'booking_instructions' => 'Contact the organiser to reserve a place.',
    'contact_name' => 'Morgan Walker',
    'capacity' => 40,
    'availability' => 'Places available',
    'accessibility_notes' => 'Step-free entrance.',
    'transport_notes' => 'Five minutes from the station.',
]);

$holiday = $createEvent(
    EventType::Holiday,
    'Coast and moor long weekend',
    'coast-and-moor-long-weekend',
    '2026-09-12 16:00:00',
    '2026-09-15 10:00:00',
    'Big skies, coastal paths and an easygoing base for the weekend.',
);
app(SaveHolidayDetails::class)->handle($holiday, [
    'destination' => 'Coast and moorland',
    'accommodation' => 'A friendly stone lodge close to the coast.',
    'pricing_type' => 'from',
    'price_amount' => '325.00',
    'currency' => 'GBP',
    'deposit_amount' => '75.00',
    'pricing_notes' => 'Final balance is paid to the accommodation.',
    'capacity' => 24,
    'availability' => 'Six places left',
    'booking_status' => 'Booking open',
    'booking_deadline' => '2026-09-01 17:00:00',
    'booking_instructions' => 'Use the external trip information to contact the organiser.',
    'booking_url' => 'https://example.com/weekend',
    'booking_contact' => 'Morgan Walker',
    'travel_details' => 'Rail connections and shared lifts are available.',
    'itinerary_notes' => 'Friday arrival, two full walking days and Monday departure.',
    'featured_image_path' => '/images/demo/coastal-weekend.png',
]);

$childWalk = $createEvent(
    EventType::Walk,
    'Clifftop circuit',
    'clifftop-circuit',
    '2026-09-13 09:30:00',
    '2026-09-13 15:30:00',
    'A coastal circuit from the weekend base.',
);
app(SaveWalkDetails::class)->handle($childWalk, [
    'primary_leader_id' => $leader->id,
    'grade_id' => $grade->id,
    'distance' => 8.0,
    'ascent' => 280,
    'meeting_location_name' => 'Seaview Lodge',
    'availability' => 'Places available',
    'featured_image_path' => '/images/demo/coastal-weekend.png',
]);
app(AssignHolidayChild::class)->handle($holiday, $childWalk);

$childSocial = $createEvent(
    EventType::Social,
    'Saturday lodge supper',
    'saturday-lodge-supper',
    '2026-09-13 19:00:00',
    '2026-09-13 22:00:00',
    'A shared meal after the coastal walk.',
);
app(SaveSocialDetails::class)->handle($childSocial, ['venue_name' => 'Seaview Lodge']);
app(AssignHolidayChild::class)->handle($holiday, $childSocial);
