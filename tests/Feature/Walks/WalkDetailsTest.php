<?php

namespace Tests\Feature\Walks;

use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Operations\Actions\UpdateSiteProfile;
use App\Domain\Walks\Actions\SaveWalkDetails;
use App\Domain\Walks\Data\WalkMeasurements;
use App\Domain\Walks\Data\WalkPublicDetails;
use App\Domain\Walks\Models\Grade;
use App\Domain\Walks\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class WalkDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_saves_walk_details_against_an_event_with_existing_leaders_grade_and_tags(): void
    {
        $event = Event::factory()->create();
        $primaryLeader = User::factory()->create();
        $coLeader = User::factory()->create();
        $grade = Grade::query()->create([
            'display_order' => 10,
            'name' => 'Moderate',
            'description' => 'A steady walk over mixed terrain.',
        ]);
        $tag = Tag::query()->create(['name' => 'Riverside']);

        $walk = app(SaveWalkDetails::class)->handle($event, [
            'primary_leader_id' => $primaryLeader->id,
            'co_leader_ids' => [$coLeader->id],
            'grade_id' => $grade->id,
            'tag_ids' => [$tag->id],
            'distance' => 8.5,
            'ascent' => 240,
            'estimated_duration_minutes' => 270,
        ]);

        $this->assertTrue($walk->event->is($event));
        $this->assertTrue($walk->primaryLeader->is($primaryLeader));
        $this->assertTrue($walk->grade->is($grade));
        $this->assertSame([$coLeader->id], $walk->coLeaders->modelKeys());
        $this->assertSame([$tag->id], $walk->tags->modelKeys());
        $this->assertSame('8.50', $walk->distance);
        $this->assertSame('240.00', $walk->ascent);
        $this->assertSame(270, $walk->estimated_duration_minutes);
    }

    public function test_rejects_a_latitude_without_its_paired_longitude(): void
    {
        $event = Event::factory()->create();
        $leader = User::factory()->create();

        try {
            app(SaveWalkDetails::class)->handle($event, [
                'primary_leader_id' => $leader->id,
                'latitude' => 52.9548,
            ]);

            $this->fail('An unpaired coordinate was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('longitude', $exception->errors());
        }
    }

    public function test_rejects_coordinates_outside_their_geographic_ranges(): void
    {
        $event = Event::factory()->create();
        $leader = User::factory()->create();

        try {
            app(SaveWalkDetails::class)->handle($event, [
                'primary_leader_id' => $leader->id,
                'latitude' => 90.000001,
                'longitude' => -180.000001,
            ]);

            $this->fail('Out-of-range coordinates were accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('latitude', $exception->errors());
            $this->assertArrayHasKey('longitude', $exception->errors());
        }
    }

    public function test_rejects_non_positive_distance_duration_and_negative_ascent_or_capacity(): void
    {
        $event = Event::factory()->create();
        $leader = User::factory()->create();

        try {
            app(SaveWalkDetails::class)->handle($event, [
                'primary_leader_id' => $leader->id,
                'distance' => 0,
                'ascent' => -1,
                'estimated_duration_minutes' => 0,
                'capacity' => -1,
            ]);

            $this->fail('Invalid walk metrics were accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('distance', $exception->errors());
            $this->assertArrayHasKey('ascent', $exception->errors());
            $this->assertArrayHasKey('estimated_duration_minutes', $exception->errors());
            $this->assertArrayHasKey('capacity', $exception->errors());
        }
    }

    public function test_requires_transport_detail_when_a_walk_is_marked_public_transport_friendly(): void
    {
        $event = Event::factory()->create();
        $leader = User::factory()->create();

        try {
            app(SaveWalkDetails::class)->handle($event, [
                'primary_leader_id' => $leader->id,
                'is_public_transport_friendly' => true,
            ]);

            $this->fail('A transport-friendly walk without transport detail was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('public_transport', $exception->errors());
        }
    }

    public function test_persists_the_optional_walk_detail_seams_without_creating_public_content(): void
    {
        $event = Event::factory()->create();
        $leader = User::factory()->create();

        $walk = app(SaveWalkDetails::class)->handle($event, [
            'primary_leader_id' => $leader->id,
            'terrain_notes' => 'Steep woodland paths after rain.',
            'meeting_location_name' => 'North gate',
            'meeting_address' => '1 Example Lane',
            'meeting_postcode' => 'NG1 1AA',
            'latitude' => 52.9548,
            'longitude' => -1.1581,
            'what3words' => '///drift.windy.pins',
            'os_grid_reference' => 'SK 570 400',
            'directions' => 'Meet beside the noticeboard.',
            'parking_notes' => 'Use the council car park.',
            'is_public_transport_friendly' => true,
            'public_transport_station_stop' => 'Central Station',
            'public_transport_notes' => 'Five minutes on foot.',
            'public_transport_url' => 'https://example.test/travel',
            'toilet_information' => 'Available at the visitor centre.',
            'cafe_pub_information' => 'Café stop after the walk.',
            'dog_guidance' => 'Dogs on leads near livestock.',
            'accessibility_notes' => 'Uneven ground throughout.',
            'kit_checklist' => ['Waterproof', 'Water'],
            'kit_notes' => 'Bring a packed lunch.',
            'availability' => 'Spaces available',
            'featured_image_path' => 'walks/featured/north-gate.jpg',
            'attachments' => [[
                'path' => 'walks/attachments/route-sheet.pdf',
                'name' => 'Route sheet.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => 2048,
            ]],
            'gpx_path' => 'walks/gpx/north-gate.gpx',
            'gpx_derived_metadata' => ['distance_metres' => 13680],
            'private_organiser_notes' => 'Check the gate is unlocked.',
            'recap' => 'Dry weather and good views.',
            'highlights' => 'Kingfisher sighting.',
        ]);

        $walk->refresh();

        $this->assertSame('Steep woodland paths after rain.', $walk->terrain_notes);
        $this->assertSame('North gate', $walk->meeting_location_name);
        $this->assertSame('NG1 1AA', $walk->meeting_postcode);
        $this->assertSame('///drift.windy.pins', $walk->what3words);
        $this->assertSame('SK 570 400', $walk->os_grid_reference);
        $this->assertTrue($walk->is_public_transport_friendly);
        $this->assertSame('https://example.test/travel', $walk->public_transport_url);
        $this->assertSame(['Waterproof', 'Water'], $walk->kit_checklist);
        $this->assertSame('Spaces available', $walk->availability);
        $this->assertSame('walks/featured/north-gate.jpg', $walk->featured_image_path);
        $this->assertSame('walks/attachments/route-sheet.pdf', $walk->attachments[0]['path']);
        $this->assertSame('walks/gpx/north-gate.gpx', $walk->gpx_path);
        $this->assertSame(['distance_metres' => 13680], $walk->gpx_derived_metadata);
        $this->assertSame('Check the gate is unlocked.', $walk->private_organiser_notes);
        $this->assertSame('Dry weather and good views.', $walk->recap);
        $this->assertSame('Kingfisher sighting.', $walk->highlights);
    }

    public function test_rejects_a_primary_leader_repeated_as_a_co_leader(): void
    {
        $event = Event::factory()->create();
        $leader = User::factory()->create();

        try {
            app(SaveWalkDetails::class)->handle($event, [
                'primary_leader_id' => $leader->id,
                'co_leader_ids' => [$leader->id],
            ]);

            $this->fail('A primary leader was accepted as their own co-leader.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('co_leader_ids', $exception->errors());
        }
    }

    public function test_rejects_overlong_structured_walk_arrays(): void
    {
        $event = Event::factory()->create();
        $leader = User::factory()->create();

        try {
            app(SaveWalkDetails::class)->handle($event, [
                'primary_leader_id' => $leader->id,
                'kit_checklist' => array_fill(0, 21, 'Water'),
                'attachments' => array_fill(0, 11, [
                    'path' => 'walks/attachments/route-sheet.pdf',
                    'name' => 'Route sheet.pdf',
                ]),
            ]);

            $this->fail('Overlong structured walk arrays were accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('kit_checklist', $exception->errors());
            $this->assertArrayHasKey('attachments', $exception->errors());
        }
    }

    public function test_measurement_data_uses_the_installation_wide_units(): void
    {
        $profile = app(UpdateSiteProfile::class)->handle([
            'group_name' => 'Example Walkers',
            'distance_unit' => 'kilometres',
            'ascent_unit' => 'metres',
        ]);
        $walk = app(SaveWalkDetails::class)->handle(Event::factory()->create(), [
            'primary_leader_id' => User::factory()->create()->id,
            'distance' => 13.5,
            'ascent' => 420,
        ]);

        $measurements = WalkMeasurements::from($walk, $profile);

        $this->assertSame('13.50', $measurements->distance);
        $this->assertSame('kilometres', $measurements->distanceUnit);
        $this->assertSame('420.00', $measurements->ascent);
        $this->assertSame('metres', $measurements->ascentUnit);
    }

    public function test_public_detail_data_omits_empty_optional_sections_and_private_notes(): void
    {
        $walk = app(SaveWalkDetails::class)->handle(Event::factory()->create(), [
            'primary_leader_id' => User::factory()->create()->id,
            'private_organiser_notes' => 'Arrange key collection.',
        ]);

        $details = WalkPublicDetails::from($walk);

        $this->assertSame([], $details->sections());
    }

    public function test_rejects_a_malformed_public_transport_link(): void
    {
        $event = Event::factory()->create();
        $leader = User::factory()->create();

        try {
            app(SaveWalkDetails::class)->handle($event, [
                'primary_leader_id' => $leader->id,
                'public_transport_url' => 'not a link',
            ]);

            $this->fail('A malformed public transport link was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('public_transport_url', $exception->errors());
        }
    }

    public function test_rejects_walk_details_for_a_non_walk_event(): void
    {
        $event = Event::factory()->create(['type' => EventType::Social]);

        $this->expectException(ValidationException::class);

        app(SaveWalkDetails::class)->handle($event, [
            'primary_leader_id' => User::factory()->create()->id,
        ]);
    }
}
