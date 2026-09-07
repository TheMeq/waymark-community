<?php

namespace Tests\Feature\Walks;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Walks\Models\Walk;
use App\Filament\Resources\WalkResource\Pages\CreateWalk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class WalkDraftWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_step_one_stays_on_the_first_step_without_creating_a_draft(): void
    {
        $leader = User::factory()->walkLeader()->create();

        Livewire::actingAs($leader)
            ->test(CreateWalk::class)
            ->assertWizardCurrentStep(1)
            ->goToNextWizardStep()
            ->assertNotDispatched('next-wizard-step')
            ->assertHasFormErrors([
                'title' => 'required',
                'starts_at' => 'required',
            ]);

        $this->assertDatabaseCount('events', 0);
        $this->assertDatabaseCount('walks', 0);
    }

    public function test_valid_step_one_creates_the_first_private_draft_and_advances(): void
    {
        $leader = User::factory()->walkLeader()->create();

        $component = Livewire::actingAs($leader)
            ->test(CreateWalk::class)
            ->fillForm([
                'title' => 'Reservoir circuit',
                'starts_at' => '2026-09-12 09:30:00',
                'meeting_location_name' => 'North gate',
            ])
            ->goToNextWizardStep()
            ->assertWizardCurrentStep(2)
            ->assertSet('draftId', fn (?int $id): bool => filled($id))
            ->assertSee('Draft saved');

        $draft = Walk::query()->with('event')->findOrFail($component->get('draftId'));

        $this->assertSame(EventStatus::Draft, $draft->event->status);
        $this->assertFalse($draft->event->is_public);
        $this->assertNull($draft->event->published_at);
        $this->assertSame($leader->id, $draft->event->organiser_id);
        $this->assertSame($leader->id, $draft->primary_leader_id);
        $this->assertSame('North gate', $draft->meeting_location_name);
        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseCount('walks', 1);
    }

    public function test_back_and_next_update_the_first_draft_without_duplication(): void
    {
        $leader = User::factory()->walkLeader()->create();

        Livewire::actingAs($leader)
            ->test(CreateWalk::class)
            ->fillForm([
                'title' => 'Reservoir circuit',
                'starts_at' => '2026-09-12 09:30:00',
            ])
            ->goToNextWizardStep()
            ->goToPreviousWizardStep()
            ->fillForm(['meeting_location_name' => 'Updated gate'])
            ->goToNextWizardStep()
            ->assertWizardCurrentStep(2)
            ->assertFormSet(['primary_leader_id' => $leader->id]);

        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseCount('walks', 1);
        $this->assertSame('Updated gate', Walk::query()->sole()->meeting_location_name);
    }
}
