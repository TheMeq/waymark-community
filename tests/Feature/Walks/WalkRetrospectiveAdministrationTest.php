<?php

namespace Tests\Feature\Walks;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventUpdate;
use App\Domain\Walks\Actions\SaveWalkDetails;
use App\Filament\Resources\WalkResource\Pages\EditWalk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class WalkRetrospectiveAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_organiser_can_add_an_update_and_open_the_focused_update_history_from_walk_admin(): void
    {
        $organiser = User::factory()->create(['can_manage_walks' => true]);
        $event = Event::factory()->for($organiser, 'organiser')->create([
            'status' => EventStatus::Published,
            'is_public' => true,
            'published_at' => now()->subMinute(),
        ]);
        $walk = app(SaveWalkDetails::class)->handle($event, ['primary_leader_id' => $organiser->id]);

        $this->actingAs($organiser);

        Livewire::test(EditWalk::class, ['record' => $walk->id])
            ->assertActionExists('addUpdate')
            ->assertActionExists('viewUpdateHistory')
            ->callAction('addUpdate', data: [
                'message' => 'The meeting point has changed.',
                'is_significant' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas(EventUpdate::class, [
            'event_id' => $event->id,
            'author_id' => $organiser->id,
            'message' => 'The meeting point has changed.',
            'is_significant' => true,
        ]);
    }

    public function test_completed_walk_shows_a_focused_recap_action_in_admin(): void
    {
        $organiser = User::factory()->create(['can_manage_walks' => true]);
        $event = Event::factory()->for($organiser, 'organiser')->create([
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);
        $walk = app(SaveWalkDetails::class)->handle($event, ['primary_leader_id' => $organiser->id]);

        $this->actingAs($organiser);

        Livewire::test(EditWalk::class, ['record' => $walk->id])
            ->assertActionVisible('saveRecap')
            ->callAction('saveRecap', data: [
                'recap' => 'A good day out.',
                'highlights' => 'Views across the valley.',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('walks', [
            'id' => $walk->id,
            'recap' => 'A good day out.',
            'highlights' => 'Views across the valley.',
        ]);
    }
}
