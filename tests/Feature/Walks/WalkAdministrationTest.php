<?php

namespace Tests\Feature\Walks;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Walks\Actions\SubmitWalkForPublication;
use App\Domain\Walks\Actions\UpdateWalkFieldSettings;
use App\Domain\Walks\Models\Walk;
use App\Filament\Resources\WalkResource\Pages\CreateWalk;
use App\Filament\Resources\WalkResource\Pages\EditWalk;
use App\Models\User;
use App\Policies\WalkPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_walk_leader_creates_a_walk_owned_by_themself_and_publishes_when_direct_publish_is_enabled(): void
    {
        $leader = User::factory()->create(['can_manage_walks' => true]);
        app(UpdateWalkFieldSettings::class)->handle([], leadersCanPublishDirectly: true);

        $this->actingAs($leader);

        Livewire::test(CreateWalk::class)
            ->fillForm([
                'title' => 'Reservoir circuit',
                'slug' => 'reservoir-circuit',
                'starts_at' => '2026-09-12 09:30:00',
                'ends_at' => '2026-09-12 14:30:00',
                'primary_leader_id' => $leader->id,
                'distance' => 8.5,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $event = Event::query()->where('slug', 'reservoir-circuit')->firstOrFail();

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
                'slug' => 'canal-path-walk',
                'starts_at' => '2026-09-19 09:30:00',
                'primary_leader_id' => $leader->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $event = Event::query()->where('slug', 'canal-path-walk')->firstOrFail();

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
