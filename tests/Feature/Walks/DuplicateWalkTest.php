<?php

namespace Tests\Feature\Walks;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Walks\Actions\DuplicateWalk;
use App\Domain\Walks\Actions\SaveWalkDetails;
use App\Domain\Walks\Enums\DuplicateWalkCopyGroup;
use App\Domain\Walks\Models\Grade;
use App\Domain\Walks\Models\Tag;
use App\Domain\Walks\Models\Walk;
use App\Filament\Resources\WalkResource\Pages\ListWalks;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class DuplicateWalkTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_organiser_can_open_the_duplicate_walk_review_action(): void
    {
        $organiser = User::factory()->create(['can_manage_walks' => true]);
        $walk = $this->createSourceWalk($organiser);

        $this->actingAs($organiser);

        Livewire::test(ListWalks::class)
            ->assertTableActionExists('duplicateWalk', record: $walk)
            ->assertTableActionHasLabel('duplicateWalk', 'Duplicate', $walk)
            ->mountTableAction('duplicateWalk', $walk)
            ->assertTableActionDataSet([
                'copy_groups' => [
                    DuplicateWalkCopyGroup::CoreDetails->value,
                    DuplicateWalkCopyGroup::Location->value,
                ],
            ]);
    }

    public function test_duplicate_resets_event_and_retrospective_state_without_changing_the_source(): void
    {
        $organiser = User::factory()->create(['can_manage_walks' => true]);
        $source = $this->createSourceWalk($organiser);

        $duplicate = app(DuplicateWalk::class)->handle($source, $organiser, $this->duplicateOptions([
            DuplicateWalkCopyGroup::CoreDetails->value,
            DuplicateWalkCopyGroup::OptionalFields->value,
        ]));

        $duplicate->load(['event', 'coLeaders', 'tags']);
        $source->refresh()->load(['event', 'coLeaders', 'tags']);

        $this->assertNotSame($source->id, $duplicate->id);
        $this->assertNotSame($source->event_id, $duplicate->event_id);
        $this->assertSame('Winter circuit', $duplicate->event->title);
        $this->assertSame('winter-circuit', $duplicate->event->slug);
        $this->assertSame('2026-12-13 09:00:00', $duplicate->event->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-13 14:00:00', $duplicate->event->ends_at?->format('Y-m-d H:i:s'));
        $this->assertSame(EventStatus::Draft, $duplicate->event->status);
        $this->assertFalse($duplicate->event->is_public);
        $this->assertNull($duplicate->event->published_at);
        $this->assertNull($duplicate->event->completion_override);
        $this->assertSame($organiser->id, $duplicate->event->organiser_id);
        $this->assertNull($duplicate->recap);
        $this->assertNull($duplicate->highlights);
        $this->assertSame('Original circuit', $source->event->title);
        $this->assertSame(EventStatus::Published, $source->event->status);
        $this->assertSame('Dry weather and good views.', $source->recap);
        $this->assertSame('Kingfisher sighting.', $source->highlights);
    }

    #[DataProvider('independentCopyGroups')]
    public function test_each_copy_group_can_be_selected_independently(string $group, string $attribute, mixed $expected): void
    {
        $organiser = User::factory()->create(['can_manage_walks' => true]);
        $source = $this->createSourceWalk($organiser);

        $duplicate = app(DuplicateWalk::class)->handle($source, $organiser, $this->duplicateOptions([$group]));

        $duplicate->load(['event', 'coLeaders', 'tags']);

        if ($group === DuplicateWalkCopyGroup::CoreDetails->value) {
            $this->assertSame('Original summary', $duplicate->event->summary);
            $this->assertSame('Original description', $duplicate->event->description);
            $this->assertSame($source->grade_id, $duplicate->grade_id);
            $this->assertSame($source->primary_leader_id, $duplicate->primary_leader_id);
            $this->assertSame($source->coLeaders->modelKeys(), $duplicate->coLeaders->modelKeys());
            $this->assertSame($source->tags->modelKeys(), $duplicate->tags->modelKeys());
            $this->assertSame($source->distance, $duplicate->distance);
            $this->assertSame($source->ascent, $duplicate->ascent);
            $this->assertSame($source->estimated_duration_minutes, $duplicate->estimated_duration_minutes);
            $this->assertSame($source->capacity, $duplicate->capacity);
            $this->assertSame($source->availability, $duplicate->availability);
        } else {
            $this->assertSame($expected, $duplicate->{$attribute});
        }

        if ($group === DuplicateWalkCopyGroup::Location->value) {
            foreach ([
                'latitude', 'longitude', 'meeting_location_name', 'meeting_address', 'meeting_postcode',
                'what3words', 'os_grid_reference', 'directions', 'parking_notes',
            ] as $attribute) {
                $this->assertSame($source->{$attribute}, $duplicate->{$attribute});
            }
        }

        if ($group === DuplicateWalkCopyGroup::Gpx->value) {
            $this->assertSame($source->gpx_derived_metadata, $duplicate->gpx_derived_metadata);
        }

        if ($group === DuplicateWalkCopyGroup::OptionalFields->value) {
            foreach ([
                'terrain_notes', 'is_public_transport_friendly', 'public_transport_station_stop',
                'public_transport_notes', 'public_transport_url', 'toilet_information',
                'cafe_pub_information', 'dog_guidance', 'accessibility_notes', 'kit_checklist',
                'kit_notes', 'private_organiser_notes',
            ] as $attribute) {
                $this->assertSame($source->{$attribute}, $duplicate->{$attribute});
            }
        }

        if ($group !== DuplicateWalkCopyGroup::FeaturedImage->value) {
            $this->assertNull($duplicate->featured_image_path);
        }

        if ($group !== DuplicateWalkCopyGroup::Gpx->value) {
            $this->assertNull($duplicate->gpx_path);
        }

        if ($group !== DuplicateWalkCopyGroup::Attachments->value) {
            $this->assertNull($duplicate->attachments);
        }

        if ($group !== DuplicateWalkCopyGroup::Location->value) {
            $this->assertNull($duplicate->meeting_location_name);
        }

        if ($group !== DuplicateWalkCopyGroup::OptionalFields->value) {
            $this->assertNull($duplicate->terrain_notes);
        }
    }

    /** @return array<string, array{string, string, mixed}> */
    public static function independentCopyGroups(): array
    {
        return [
            'core details' => [DuplicateWalkCopyGroup::CoreDetails->value, 'distance', '9.25'],
            'location' => [DuplicateWalkCopyGroup::Location->value, 'meeting_location_name', 'North gate'],
            'GPX route' => [DuplicateWalkCopyGroup::Gpx->value, 'gpx_path', 'walks/gpx/north-gate.gpx'],
            'attachments' => [DuplicateWalkCopyGroup::Attachments->value, 'attachments', [[
                'path' => 'walks/attachments/route-sheet.pdf',
                'name' => 'Route sheet.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => 2048,
            ]]],
            'featured image' => [DuplicateWalkCopyGroup::FeaturedImage->value, 'featured_image_path', 'walks/featured/north-gate.jpg'],
            'optional fields' => [DuplicateWalkCopyGroup::OptionalFields->value, 'terrain_notes', 'Steep woodland paths after rain.'],
        ];
    }

    public function test_selected_groups_can_be_combined_without_copying_unselected_groups(): void
    {
        $organiser = User::factory()->create(['can_manage_walks' => true]);
        $source = $this->createSourceWalk($organiser);

        $duplicate = app(DuplicateWalk::class)->handle($source, $organiser, $this->duplicateOptions([
            DuplicateWalkCopyGroup::Location->value,
            DuplicateWalkCopyGroup::Attachments->value,
        ]));

        $this->assertSame('North gate', $duplicate->meeting_location_name);
        $this->assertSame('walks/attachments/route-sheet.pdf', $duplicate->attachments[0]['path']);
        $this->assertNull($duplicate->featured_image_path);
        $this->assertNull($duplicate->gpx_path);
        $this->assertNull($duplicate->terrain_notes);
        $this->assertNull($duplicate->event->summary);
        $this->assertSame($organiser->id, $duplicate->primary_leader_id);
        $this->assertEmpty($duplicate->coLeaders);
        $this->assertEmpty($duplicate->tags);
    }

    public function test_gpx_group_copies_the_reference_and_derived_metadata_seam(): void
    {
        $organiser = User::factory()->create(['can_manage_walks' => true]);
        $source = $this->createSourceWalk($organiser);

        $duplicate = app(DuplicateWalk::class)->handle($source, $organiser, $this->duplicateOptions([
            DuplicateWalkCopyGroup::Gpx->value,
        ]));

        $this->assertSame('walks/gpx/north-gate.gpx', $duplicate->gpx_path);
        $this->assertSame(['distance_metres' => 13680], $duplicate->gpx_derived_metadata);
    }

    public function test_duplicate_requires_a_new_unique_title_slug_and_valid_dates(): void
    {
        $organiser = User::factory()->create(['can_manage_walks' => true]);
        $source = $this->createSourceWalk($organiser);

        try {
            app(DuplicateWalk::class)->handle($source, $organiser, [
                'title' => '',
                'slug' => $source->event->slug,
                'starts_at' => '2026-12-13 09:00:00',
                'ends_at' => '2026-12-12 08:00:00',
                'copy_groups' => [],
            ]);

            $this->fail('A duplicate accepted missing identity, a duplicate slug, and invalid dates.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('title', $exception->errors());
            $this->assertArrayHasKey('slug', $exception->errors());
            $this->assertArrayHasKey('ends_at', $exception->errors());
            $this->assertSame(1, Event::query()->count());
        }

        try {
            app(DuplicateWalk::class)->handle($source, $organiser, [
                'title' => 'Another walk',
                'slug' => 'another-walk',
                'starts_at' => null,
                'copy_groups' => [],
            ]);

            $this->fail('A duplicate accepted a missing start date.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('starts_at', $exception->errors());
            $this->assertSame(1, Event::query()->count());
        }
    }

    public function test_another_walk_leader_cannot_duplicate_a_walk_they_do_not_organise(): void
    {
        $organiser = User::factory()->create(['can_manage_walks' => true]);
        $otherLeader = User::factory()->create(['can_manage_walks' => true]);
        $source = $this->createSourceWalk($organiser);

        $this->expectException(AuthorizationException::class);

        app(DuplicateWalk::class)->handle($source, $otherLeader, $this->duplicateOptions());
    }

    public function test_administrator_can_duplicate_another_organisers_walk_and_becomes_the_new_organiser(): void
    {
        $organiser = User::factory()->create(['can_manage_walks' => true]);
        $administrator = User::factory()->create(['is_admin' => true]);
        $source = $this->createSourceWalk($organiser);

        $duplicate = app(DuplicateWalk::class)->handle($source, $administrator, $this->duplicateOptions());

        $this->assertSame($administrator->id, $duplicate->event->organiser_id);
    }

    public function test_filament_duplicate_action_creates_the_reviewed_draft(): void
    {
        $organiser = User::factory()->create(['can_manage_walks' => true]);
        $source = $this->createSourceWalk($organiser);

        $this->actingAs($organiser);

        Livewire::test(ListWalks::class)
            ->callTableAction('duplicateWalk', $source, $this->duplicateOptions([
                DuplicateWalkCopyGroup::FeaturedImage->value,
            ]))
            ->assertHasNoTableActionErrors();

        $duplicate = Walk::query()->where('event_id', '!=', $source->event_id)->sole();

        $this->assertSame('Winter circuit', $duplicate->event->title);
        $this->assertSame('walks/featured/north-gate.jpg', $duplicate->featured_image_path);
        $this->assertSame(EventStatus::Draft, $duplicate->event->status);
    }

    public function test_filament_review_requires_new_identity_and_start_date(): void
    {
        $organiser = User::factory()->create(['can_manage_walks' => true]);
        $source = $this->createSourceWalk($organiser);

        $this->actingAs($organiser);

        Livewire::test(ListWalks::class)
            ->callTableAction('duplicateWalk', $source, [
                'title' => '',
                'slug' => '',
                'starts_at' => null,
                'copy_groups' => [],
            ])
            ->assertHasTableActionErrors(['title', 'slug', 'starts_at']);

        $this->assertSame(1, Event::query()->count());
    }

    public function test_walk_resource_query_excludes_another_organisers_source_walk(): void
    {
        $organiser = User::factory()->create(['can_manage_walks' => true]);
        $otherLeader = User::factory()->create(['can_manage_walks' => true]);
        $source = $this->createSourceWalk($organiser);

        $this->actingAs($otherLeader);

        Livewire::test(ListWalks::class)
            ->assertCanNotSeeTableRecords([$source]);
    }

    public function test_duplicate_rolls_back_when_selected_source_data_cannot_be_saved(): void
    {
        $organiser = User::factory()->create(['can_manage_walks' => true]);
        $source = $this->createSourceWalk($organiser);
        $source->forceFill(['attachments' => [['name' => 'Missing path.pdf']]])->save();

        try {
            app(DuplicateWalk::class)->handle($source->fresh(), $organiser, $this->duplicateOptions([
                DuplicateWalkCopyGroup::Attachments->value,
            ]));

            $this->fail('A duplicate with invalid selected attachment data was saved.');
        } catch (ValidationException) {
            $this->assertSame(1, Event::query()->count());
            $this->assertSame(1, Walk::query()->count());
        }
    }

    /** @param array<int, string>|null $copyGroups
     * @return array<string, mixed>
     */
    private function duplicateOptions(?array $copyGroups = null): array
    {
        return [
            'title' => 'Winter circuit',
            'slug' => 'winter-circuit',
            'starts_at' => '2026-12-13 09:00:00',
            'ends_at' => '2026-12-13 14:00:00',
            'copy_groups' => $copyGroups ?? [
                DuplicateWalkCopyGroup::CoreDetails->value,
                DuplicateWalkCopyGroup::Location->value,
            ],
        ];
    }

    private function createSourceWalk(User $organiser): Walk
    {
        $coLeader = User::factory()->create();
        $grade = Grade::query()->create([
            'display_order' => 10,
            'name' => 'Moderate',
            'description' => 'A steady walk over mixed terrain.',
        ]);
        $tag = Tag::query()->create(['name' => 'Riverside']);
        $event = Event::factory()->for($organiser, 'organiser')->create([
            'title' => 'Original circuit',
            'slug' => 'original-circuit',
            'summary' => 'Original summary',
            'description' => 'Original description',
            'starts_at' => '2026-09-12 09:30:00',
            'ends_at' => '2026-09-12 14:30:00',
            'status' => EventStatus::Published,
            'is_public' => true,
            'published_at' => '2026-08-20 10:00:00',
            'completion_override' => true,
        ]);

        return app(SaveWalkDetails::class)->handle($event, [
            'primary_leader_id' => $organiser->id,
            'co_leader_ids' => [$coLeader->id],
            'grade_id' => $grade->id,
            'tag_ids' => [$tag->id],
            'distance' => 9.25,
            'ascent' => 340,
            'estimated_duration_minutes' => 300,
            'capacity' => 24,
            'availability' => 'Spaces available',
            'latitude' => 52.9548,
            'longitude' => -1.1581,
            'meeting_location_name' => 'North gate',
            'meeting_address' => '1 Example Lane',
            'meeting_postcode' => 'NG1 1AA',
            'what3words' => '///drift.windy.pins',
            'os_grid_reference' => 'SK 570 400',
            'directions' => 'Meet beside the noticeboard.',
            'parking_notes' => 'Use the council car park.',
            'gpx_path' => 'walks/gpx/north-gate.gpx',
            'gpx_derived_metadata' => ['distance_metres' => 13680],
            'attachments' => [[
                'path' => 'walks/attachments/route-sheet.pdf',
                'name' => 'Route sheet.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => 2048,
            ]],
            'featured_image_path' => 'walks/featured/north-gate.jpg',
            'terrain_notes' => 'Steep woodland paths after rain.',
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
            'private_organiser_notes' => 'Check the gate is unlocked.',
            'recap' => 'Dry weather and good views.',
            'highlights' => 'Kingfisher sighting.',
        ]);
    }
}
