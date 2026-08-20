<?php

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Operations\Actions\UpdateSiteProfile;
use App\Domain\Walks\Actions\SaveWalkDetails;
use App\Domain\Walks\Models\Grade;
use App\Models\User;
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
    ['Ridge and reservoir', 'hero-walkers.png', 8.5, 250],
    ['Woodland and water', 'woodland-walk.png', 6.0, 180],
    ['Moorland views', 'lakeside-friends.png', 10.0, 430],
] as $offset => [$title, $image, $distance, $ascent]) {
    $startsAt = now()->startOfDay()->addDays($offset + 2)->setTime(9 + $offset, 30);
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
        'published_at' => now()->subMinute(),
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
