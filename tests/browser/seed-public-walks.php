<?php

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Gallery\Actions\AcceptCurrentPhotoUploadPolicy;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\Models\CommunityPhotoReport;
use App\Domain\Holidays\Actions\AssignHolidayChild;
use App\Domain\Holidays\Actions\SaveHolidayDetails;
use App\Domain\Operations\Actions\UpdateSiteProfile;
use App\Domain\Socials\Actions\SaveSocialDetails;
use App\Domain\Walks\Actions\SaveWalkDetails;
use App\Domain\Walks\Models\Grade;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Fortify;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

app(UpdateSiteProfile::class)->handle(['group_name' => 'Example Walkers']);
$grade = Grade::query()->create([
    'display_order' => 10,
    'name' => 'Moderate',
    'description' => 'A steady pace over mixed terrain.',
]);
$leader = User::factory()->create([
    'name' => 'Morgan Walker',
    'display_name' => 'Morgan W.',
    'email' => 'morgan.leader@example.test',
    'role' => AccountRole::WalkLeader,
    'public_profile_enabled' => true,
    'public_profile_slug' => 'morgan-w',
    'public_profile_introduction' => 'I enjoy sharing friendly, varied walks and helping people feel at home outdoors.',
    'profile_photo_reference' => '/images/demo/lakeside-friends.png',
]);
app(AcceptCurrentPhotoUploadPolicy::class)->handle($leader);

$moderationEvent = Event::query()->create([
    'type' => EventType::Walk,
    'title' => 'Browser moderation walk',
    'slug' => 'browser-moderation-walk',
    'summary' => 'A deterministic moderation fixture.',
    'description' => 'A deterministic moderation fixture.',
    'starts_at' => CarbonImmutable::parse('2026-08-21 09:30:00'),
    'ends_at' => CarbonImmutable::parse('2026-08-21 15:30:00'),
    'status' => EventStatus::Published,
    'is_public' => true,
    'published_at' => CarbonImmutable::parse('2026-08-19 12:00:00'),
    'organiser_id' => $leader->id,
]);
$preview = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgYGAAAAAEAAGjChXjAAAAAElFTkSuQmCC', true);
$reportingUploader = User::factory()->create();

foreach (range(1, 21) as $number) {
    Storage::disk('local')->put('community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3301/browser-'.$number.'.jpg', $preview);
    CommunityPhoto::query()->create([
        'event_id' => $moderationEvent->id,
        'uploader_id' => $leader->id,
        'media_type' => 'image',
        'processing_status' => 'complete',
        'storage_disk' => 'local',
        'source_path' => 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3301/browser-'.$number.'.jpg',
        'processed_variants' => ['master' => 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3301/browser-'.$number.'.jpg'],
        'moderation_status' => 'pending',
        'caption' => 'Browser moderation photo '.$number,
    ]);
}

Storage::disk('local')->put('community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3301/browser-published.jpg', $preview);

CommunityPhoto::query()->create([
    'event_id' => $moderationEvent->id,
    'uploader_id' => $leader->id,
    'media_type' => 'image',
    'processing_status' => 'complete',
    'storage_disk' => 'local',
    'source_path' => 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3301/browser-published.jpg',
    'processed_variants' => ['master' => 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3301/browser-published.jpg'],
    'moderation_status' => 'approved',
    'published_at' => CarbonImmutable::parse('2026-08-20 10:00:00'),
    'caption' => 'Browser published moderation photo',
]);

foreach (range(1, 3) as $number) {
    $pendingPath = 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3301/browser-own-pending-'.$number.'.jpg';
    $publishedPath = 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3301/browser-own-published-'.$number.'.jpg';
    Storage::disk('local')->put($pendingPath, $preview);
    Storage::disk('local')->put($publishedPath, $preview);
    CommunityPhoto::query()->create([
        'event_id' => $moderationEvent->id, 'uploader_id' => $leader->id, 'media_type' => 'image', 'processing_status' => 'complete', 'storage_disk' => 'local',
        'source_path' => $pendingPath, 'processed_variants' => ['master' => $pendingPath], 'moderation_status' => 'pending', 'caption' => 'Browser own pending '.$number,
    ]);
    $published = CommunityPhoto::query()->create([
        'event_id' => $moderationEvent->id, 'uploader_id' => $leader->id, 'media_type' => 'image', 'processing_status' => 'complete', 'storage_disk' => 'local',
        'source_path' => $publishedPath, 'processed_variants' => ['master' => $publishedPath], 'moderation_status' => 'approved', 'published_at' => CarbonImmutable::parse('2026-08-20 10:00:00'), 'caption' => 'Browser own published '.$number,
    ]);
    $reportPath = 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3301/browser-report-'.$number.'.jpg';
    Storage::disk('local')->put($reportPath, $preview);
    $reportPhoto = CommunityPhoto::query()->create([
        'event_id' => $moderationEvent->id, 'uploader_id' => $reportingUploader->id, 'media_type' => 'image', 'processing_status' => 'complete', 'storage_disk' => 'local',
        'source_path' => $reportPath, 'processed_variants' => ['master' => $reportPath], 'moderation_status' => 'approved', 'published_at' => CarbonImmutable::parse('2026-08-20 10:00:00'), 'caption' => 'Browser report photo '.$number,
    ]);
    CommunityPhotoReport::query()->create([
        'community_photo_id' => $reportPhoto->id, 'reason' => 'privacy', 'status' => 'open', 'detail' => 'Browser moderation report '.$number,
        'context_snapshot' => ['photo_id' => $reportPhoto->id, 'event_id' => $moderationEvent->id, 'special_album_id' => null, 'caption' => $reportPhoto->caption],
    ]);
}

foreach (['desktop', 'tablet', 'mobile'] as $viewport) {
    $uploadUser = User::factory()->create(['email' => 'browser-upload-'.$viewport.'@example.test', 'password' => 'password']);
    app(AcceptCurrentPhotoUploadPolicy::class)->handle($uploadUser);
    $uploader = User::factory()->create(['email' => 'browser-uploader-'.$viewport.'@example.test', 'password' => 'password']);
    app(AcceptCurrentPhotoUploadPolicy::class)->handle($uploader);
    $pendingPath = 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3301/browser-isolated-pending-'.$viewport.'.jpg';
    $publishedPath = 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3301/browser-isolated-published-'.$viewport.'.jpg';
    Storage::disk('local')->put($pendingPath, $preview);
    Storage::disk('local')->put($publishedPath, $preview);
    CommunityPhoto::query()->create([
        'event_id' => $moderationEvent->id, 'uploader_id' => $uploader->id, 'media_type' => 'image', 'processing_status' => 'complete', 'storage_disk' => 'local',
        'source_path' => $pendingPath, 'processed_variants' => ['master' => $pendingPath], 'moderation_status' => 'pending', 'caption' => 'Browser isolated pending '.$viewport,
    ]);
    CommunityPhoto::query()->create([
        'event_id' => $moderationEvent->id, 'uploader_id' => $uploader->id, 'media_type' => 'image', 'processing_status' => 'complete', 'storage_disk' => 'local',
        'source_path' => $publishedPath, 'processed_variants' => ['master' => $publishedPath], 'moderation_status' => 'approved', 'published_at' => CarbonImmutable::parse('2026-08-20 10:00:00'), 'caption' => 'Browser isolated published '.$viewport,
    ]);
}

$securityUser = User::factory()->create([
    'name' => 'Security Walker',
    'email' => 'security-browser@example.test',
    'password' => 'password',
]);
$securityUser->forceFill([
    'two_factor_secret' => Fortify::currentEncrypter()->encrypt('JBSWY3DPEHPK3PXP'),
    'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['browser-recovery-code'])),
    'two_factor_confirmed_at' => now(),
])->save();

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
        'private_organiser_notes' => 'Check the route access before leaving.',
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
