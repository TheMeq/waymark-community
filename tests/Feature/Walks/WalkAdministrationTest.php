<?php

namespace Tests\Feature\Walks;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Walks\Actions\CreateWalk as CreateWalkAction;
use App\Domain\Walks\Actions\SubmitWalkForPublication;
use App\Domain\Walks\Actions\UpdateWalkFieldSettings;
use App\Domain\Walks\Models\Grade;
use App\Domain\Walks\Models\Tag;
use App\Domain\Walks\Models\Walk;
use App\Filament\Resources\WalkResource\Pages\CreateWalk;
use App\Filament\Resources\WalkResource\Pages\EditWalk;
use App\Models\User;
use App\Policies\WalkPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

final class WalkAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_walk_capable_user_can_edit_a_walk_they_organise_even_when_another_user_is_the_primary_leader(): void
    {
        $organiser = User::factory()->create(['can_manage_walks' => true]);
        $primaryLeader = User::factory()->create();
        $walk = Walk::query()->create([
            'event_id' => Event::factory()->for($organiser, 'organiser')->create()->id,
            'primary_leader_id' => $primaryLeader->id,
        ]);

        $this->assertTrue((new WalkPolicy)->update($organiser, $walk));
    }

    public function test_walk_capable_user_cannot_edit_another_organisers_walk_even_when_assigned_as_primary_leader(): void
    {
        $walkLeader = User::factory()->create(['can_manage_walks' => true]);
        $otherOrganiser = User::factory()->create(['can_manage_walks' => true]);
        $walk = Walk::query()->create([
            'event_id' => Event::factory()->for($otherOrganiser, 'organiser')->create()->id,
            'primary_leader_id' => $walkLeader->id,
        ]);

        $this->assertFalse((new WalkPolicy)->update($walkLeader, $walk));
    }

    public function test_verified_walk_capable_user_can_access_the_admin_surface(): void
    {
        $walkLeader = User::factory()->create(['can_manage_walks' => true]);

        $this->actingAs($walkLeader)
            ->get('/admin')
            ->assertSuccessful();
    }

    public function test_verified_walk_capable_user_can_access_the_walk_resource_index(): void
    {
        $walkLeader = User::factory()->create(['can_manage_walks' => true]);

        $this->actingAs($walkLeader)
            ->get('/admin/walks')
            ->assertSuccessful();
    }

    public function test_create_form_loads_shared_walk_settings_and_leader_options_once(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);
        User::factory()->walkLeader()->create();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = str_replace(['"', '`'], '', strtolower($query->sql));
        });

        $this->actingAs($administrator);
        Livewire::test(CreateWalk::class)
            ->assertFormFieldVisible('terrain_notes')
            ->assertFormFieldVisible('primary_leader_id')
            ->assertFormFieldVisible('co_leader_ids');

        $this->assertCount(1, collect($queries)->filter(fn (string $sql): bool => str_contains($sql, 'from walk_field_settings')));
        $this->assertCount(1, collect($queries)->filter(fn (string $sql): bool => str_contains($sql, 'select distinct role from role_capabilities')));
        $this->assertCount(1, collect($queries)->filter(fn (string $sql): bool => str_contains($sql, 'select name, id from users')));
    }

    public function test_create_walk_uses_the_guided_five_step_form(): void
    {
        $administrator = User::factory()->initialAdministrator()->create();

        $page = Livewire::actingAs($administrator)->test(CreateWalk::class)->instance();
        $labels = collect($page->getSteps())->map(fn ($step): string => $step->getLabel())->all();

        $this->assertSame([
            'When and where',
            'Walk details',
            'Travel and practical information',
            'Description, route and image',
            'Leader and publishing',
        ], $labels);
    }

    public function test_walk_list_exposes_an_obvious_add_walk_action(): void
    {
        $administrator = User::factory()->initialAdministrator()->create();

        $this->actingAs($administrator)
            ->get('/admin/walks')
            ->assertSuccessful()
            ->assertSee('Add a walk')
            ->assertSee('/admin/walks/create', false);
    }

    public function test_direct_publish_setting_publishes_an_organisers_walk(): void
    {
        $organiser = User::factory()->create(['can_manage_walks' => true]);
        $walk = Walk::query()->create([
            'event_id' => Event::factory()->for($organiser, 'organiser')->create()->id,
            'primary_leader_id' => $organiser->id,
        ]);
        app(UpdateWalkFieldSettings::class)->handle([], leadersCanPublishDirectly: true);

        app(SubmitWalkForPublication::class)->handle($walk, $organiser);

        $walk->event->refresh();

        $this->assertSame(EventStatus::Published, $walk->event->status);
        $this->assertTrue($walk->event->is_public);
    }

    public function test_two_argument_create_walk_call_remains_a_one_shot_submission(): void
    {
        $administrator = User::factory()->initialAdministrator()->create();

        $walk = app(CreateWalkAction::class)->handle($administrator, [
            'title' => 'One shot circuit',
            'starts_at' => '2026-09-12 09:30:00',
            'primary_leader_id' => $administrator->id,
        ]);

        $this->assertSame(EventStatus::Published, $walk->event->status);
        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseCount('walks', 1);
    }

    public function test_walk_leader_creates_a_walk_owned_by_themself_and_publishes_when_direct_publish_is_enabled(): void
    {
        $leader = User::factory()->create(['can_manage_walks' => true]);
        app(UpdateWalkFieldSettings::class)->handle([], leadersCanPublishDirectly: true);

        $this->actingAs($leader);

        Livewire::test(CreateWalk::class)
            ->fillForm([
                'title' => 'Reservoir circuit',
                'starts_at' => '2026-09-12 09:30:00',
                'ends_at' => '2026-09-12 14:30:00',
                'primary_leader_id' => $leader->id,
                'distance' => 8.5,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $event = Event::query()->where('slug', 'reservoir-circuit-2026-09-12')->firstOrFail();

        $this->assertSame($leader->id, $event->organiser_id);
        $this->assertSame(EventStatus::Published, $event->status);
        $this->assertTrue($event->is_public);
        $this->assertSame($leader->id, $event->walk->primary_leader_id);
    }

    public function test_walk_leader_submission_is_pending_approval_when_direct_publish_is_disabled(): void
    {
        $leader = User::factory()->create(['can_manage_walks' => true]);
        app(UpdateWalkFieldSettings::class)->handle([], leadersCanPublishDirectly: false);

        $this->actingAs($leader);

        Livewire::test(CreateWalk::class)
            ->fillForm([
                'title' => 'Canal path walk',
                'starts_at' => '2026-09-19 09:30:00',
                'primary_leader_id' => $leader->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $event = Event::query()->where('slug', 'canal-path-walk-2026-09-19')->firstOrFail();

        $this->assertSame(EventStatus::PendingApproval, $event->status);
        $this->assertFalse($event->is_public);
        $this->assertNull($event->published_at);
    }

    public function test_walk_leader_cannot_open_another_organisers_filament_edit_page(): void
    {
        $walkLeader = User::factory()->create(['can_manage_walks' => true]);
        $otherOrganiser = User::factory()->create(['can_manage_walks' => true]);
        $walk = Walk::query()->create([
            'event_id' => Event::factory()->for($otherOrganiser, 'organiser')->create()->id,
            'primary_leader_id' => $walkLeader->id,
        ]);

        $this->actingAs($walkLeader)
            ->get("/admin/walks/{$walk->id}/edit")
            ->assertNotFound();
    }

    public function test_walk_leader_can_edit_their_own_walk_from_filament(): void
    {
        $leader = User::factory()->create(['can_manage_walks' => true]);
        $walk = Walk::query()->create([
            'event_id' => Event::factory()->for($leader, 'organiser')->create([
                'title' => 'Woodland route',
                'slug' => 'woodland-route',
                'starts_at' => '2026-09-20 09:30:00',
            ])->id,
            'primary_leader_id' => $leader->id,
        ]);

        $this->actingAs($leader);

        Livewire::test(EditWalk::class, ['record' => $walk->id])
            ->fillForm(['title' => 'Woodland route revised'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Woodland route revised', $walk->event->fresh()->title);
    }

    public function test_walk_edit_form_exposes_and_persists_the_complete_phase_three_walk_data_set(): void
    {
        $leader = User::factory()->create(['can_manage_walks' => true]);
        $coLeader = User::factory()->walkLeader()->create();
        $grade = Grade::query()->create(['display_order' => 10, 'name' => 'Moderate', 'description' => 'Steady']);
        $tag = Tag::query()->create(['name' => 'Riverside']);
        $walk = Walk::query()->create([
            'event_id' => Event::factory()->for($leader, 'organiser')->create(['title' => 'Full form walk', 'slug' => 'full-form-walk'])->id,
            'primary_leader_id' => $leader->id,
        ]);

        $this->actingAs($leader);
        $component = Livewire::test(EditWalk::class, ['record' => $walk->id]);
        foreach (['grade_id', 'tag_ids', 'co_leader_ids', 'terrain_notes', 'meeting_location_name', 'meeting_address', 'meeting_postcode', 'latitude', 'longitude', 'what3words', 'os_grid_reference', 'directions', 'is_public_transport_friendly', 'public_transport_station_stop', 'public_transport_notes', 'public_transport_url', 'parking_notes', 'toilet_information', 'cafe_pub_information', 'dog_guidance', 'accessibility_notes', 'kit_checklist', 'kit_notes', 'capacity', 'availability', 'featured_image_path', 'attachments', 'private_organiser_notes'] as $field) {
            $component->assertFormFieldVisible($field);
        }
        $component->fillForm([
            'grade_id' => $grade->id,
            'tag_ids' => [$tag->id],
            'co_leader_ids' => [$coLeader->id],
            'terrain_notes' => 'Rocky ground.',
            'meeting_location_name' => 'North gate',
            'meeting_address' => '1 Example Lane',
            'meeting_postcode' => 'AB1 2CD',
            'latitude' => 52.95,
            'longitude' => -1.16,
            'what3words' => '///moss.path.hill',
            'os_grid_reference' => 'SK 570 400',
            'directions' => 'Meet at the gate.',
            'is_public_transport_friendly' => true,
            'public_transport_station_stop' => 'Central station',
            'public_transport_notes' => 'Five minutes on foot.',
            'public_transport_url' => 'https://example.test/travel',
            'parking_notes' => 'Use signed bays.',
            'toilet_information' => 'At the visitor centre.',
            'cafe_pub_information' => 'Café afterwards.',
            'dog_guidance' => 'Keep dogs on leads.',
            'accessibility_notes' => 'Uneven ground.',
            'kit_checklist' => ['Water', 'Waterproof'],
            'kit_notes' => 'Bring lunch.',
            'capacity' => 20,
            'availability' => 'Spaces available',
            'featured_image_path' => 'walks/featured/north-gate.jpg',
            'attachments' => [['path' => 'walks/attachments/route-sheet.pdf', 'name' => 'Route sheet.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 2048]],
            'private_organiser_notes' => 'Check gate access.',
        ])->call('save')->assertHasNoFormErrors();

        $walk->refresh()->load(['coLeaders', 'tags']);
        $this->assertSame($grade->id, $walk->grade_id);
        $this->assertSame([$coLeader->id], $walk->coLeaders->modelKeys());
        $this->assertSame([$tag->id], $walk->tags->modelKeys());
        $this->assertSame('Rocky ground.', $walk->terrain_notes);
        $this->assertSame('North gate', $walk->meeting_location_name);
        $this->assertSame('Central station', $walk->public_transport_station_stop);
        $this->assertSame(['Water', 'Waterproof'], $walk->kit_checklist);
        $this->assertSame('walks/attachments/route-sheet.pdf', $walk->attachments[0]['path']);
        $this->assertSame('Check gate access.', $walk->private_organiser_notes);
    }

    public function test_disabled_optional_fields_are_hidden_on_the_walk_form_without_erasing_existing_values(): void
    {
        $leader = User::factory()->create(['can_manage_walks' => true]);
        $walk = Walk::query()->create([
            'event_id' => Event::factory()->for($leader, 'organiser')->create()->id,
            'primary_leader_id' => $leader->id,
            'terrain_notes' => 'Rocky ground.',
        ]);
        app(UpdateWalkFieldSettings::class)->handle(['terrain_notes' => false, 'gpx' => false]);

        $this->actingAs($leader);
        Livewire::test(EditWalk::class, ['record' => $walk->id])
            ->assertFormFieldHidden('terrain_notes')
            ->assertActionHidden('uploadGpx')
            ->fillForm(['title' => 'A changed title'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Rocky ground.', $walk->fresh()->terrain_notes);
    }

    public function test_authorised_editor_uploads_valid_gpx_through_the_filament_header_action(): void
    {
        Storage::fake('local');
        config()->set('walks.gpx.disk', 'local');
        $leader = User::factory()->create(['can_manage_walks' => true]);
        $walk = Walk::query()->create([
            'event_id' => Event::factory()->for($leader, 'organiser')->create()->id,
            'primary_leader_id' => $leader->id,
        ]);

        $this->actingAs($leader);
        Livewire::test(EditWalk::class, ['record' => $walk->id])
            ->mountAction('uploadGpx')
            ->upload('mountedActions.0.data.gpx', [UploadedFile::fake()->createWithContent('route.gpx', '<gpx version="1.1"><trk><trkseg><trkpt lat="52.95" lon="-1.16"/><trkpt lat="52.96" lon="-1.15"/></trkseg></trk></gpx>')])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertMatchesRegularExpression('#^walks/gpx/[0-9a-f-]+\.gpx$#', (string) $walk->fresh()->gpx_path);
    }

    public function test_filament_gpx_action_uses_domain_validation_for_invalid_uploads(): void
    {
        $leader = User::factory()->create(['can_manage_walks' => true]);
        $walk = Walk::query()->create([
            'event_id' => Event::factory()->for($leader, 'organiser')->create()->id,
            'primary_leader_id' => $leader->id,
        ]);

        $this->actingAs($leader);
        Livewire::test(EditWalk::class, ['record' => $walk->id])
            ->mountAction('uploadGpx')
            ->upload('mountedActions.0.data.gpx', [UploadedFile::fake()->createWithContent('route.gpx', '<!DOCTYPE gpx SYSTEM "https://attacker.test/route.dtd"><gpx/>')])
            ->callMountedAction()
            ->assertHasActionErrors(['gpx']);

        $this->assertNull($walk->fresh()->gpx_path);
    }

    public function test_focused_walk_edit_preserves_existing_optional_walk_details(): void
    {
        $leader = User::factory()->create(['can_manage_walks' => true]);
        $walk = Walk::query()->create([
            'event_id' => Event::factory()->for($leader, 'organiser')->create([
                'title' => 'Hill route',
                'slug' => 'hill-route',
                'starts_at' => '2026-09-21 09:30:00',
            ])->id,
            'primary_leader_id' => $leader->id,
            'terrain_notes' => 'Steep, rocky ground after the first gate.',
        ]);

        $this->actingAs($leader);

        Livewire::test(EditWalk::class, ['record' => $walk->id])
            ->fillForm(['title' => 'Hill route revised'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Steep, rocky ground after the first gate.', $walk->fresh()->terrain_notes);
    }

    public function test_administrator_can_edit_and_publish_another_organisers_walk_from_filament(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);
        $organiser = User::factory()->create(['can_manage_walks' => true]);
        $walk = Walk::query()->create([
            'event_id' => Event::factory()->for($organiser, 'organiser')->create([
                'title' => 'Original route',
                'slug' => 'original-route',
                'starts_at' => '2026-09-26 09:30:00',
            ])->id,
            'primary_leader_id' => $organiser->id,
        ]);

        $this->actingAs($administrator);

        Livewire::test(EditWalk::class, ['record' => $walk->id])
            ->assertActionVisible('uploadGpx')
            ->fillForm(['title' => 'Revised route'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->callAction('submitForPublication');

        $walk->event->refresh();

        $this->assertSame('Revised route', $walk->event->title);
        $this->assertSame(EventStatus::Published, $walk->event->status);
        $this->assertTrue($walk->event->is_public);
    }
}
