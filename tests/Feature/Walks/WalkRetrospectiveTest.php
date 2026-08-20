<?php

namespace Tests\Feature\Walks;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Walks\Actions\SaveWalkDetails;
use App\Domain\Walks\Actions\UpdateWalkRecap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class WalkRetrospectiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_walk_can_gain_optional_recap_and_highlights_without_losing_original_details(): void
    {
        $organiser = User::factory()->create(['can_manage_walks' => true]);
        $event = Event::factory()->for($organiser, 'organiser')->create([
            'type' => EventType::Walk,
            'status' => EventStatus::Published,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);
        $walk = app(SaveWalkDetails::class)->handle($event, [
            'primary_leader_id' => $organiser->id,
            'distance' => 8.5,
            'terrain_notes' => 'Rocky ridge paths.',
            'meeting_location_name' => 'North gate',
        ]);
        $walk->forceFill([
            'gpx_path' => 'walks/gpx/123e4567-e89b-12d3-a456-426614174000.gpx',
            'attachments' => [['path' => 'walks/attachments/route.pdf', 'name' => 'Route.pdf']],
        ])->save();

        app(UpdateWalkRecap::class)->handle($walk, $organiser, [
            'recap' => 'Clear skies and a steady pace.',
            'highlights' => 'A kestrel above the ridge.',
        ]);

        $walk->refresh();

        $this->assertSame('Clear skies and a steady pace.', $walk->recap);
        $this->assertSame('A kestrel above the ridge.', $walk->highlights);
        $this->assertSame('8.50', $walk->distance);
        $this->assertSame('Rocky ridge paths.', $walk->terrain_notes);
        $this->assertSame('North gate', $walk->meeting_location_name);
        $this->assertSame('walks/gpx/123e4567-e89b-12d3-a456-426614174000.gpx', $walk->gpx_path);
        $this->assertSame('Route.pdf', $walk->attachments[0]['name']);
    }

    public function test_recap_requires_a_completed_or_past_walk_and_plain_text(): void
    {
        $organiser = User::factory()->create(['can_manage_walks' => true]);
        $event = Event::factory()->for($organiser, 'organiser')->create(['type' => EventType::Walk]);
        $walk = app(SaveWalkDetails::class)->handle($event, ['primary_leader_id' => $organiser->id]);

        try {
            app(UpdateWalkRecap::class)->handle($walk, $organiser, ['recap' => 'Too soon.']);
            $this->fail('A current walk accepted a recap.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('walk', $exception->errors());
        }

        $event->forceFill(['completion_override' => true])->save();

        try {
            app(UpdateWalkRecap::class)->handle($walk, $organiser, ['recap' => '<em>Not plain text</em>']);
            $this->fail('Markup recap was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('recap', $exception->errors());
        }
    }
}
