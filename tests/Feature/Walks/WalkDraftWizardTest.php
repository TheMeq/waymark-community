<?php

namespace Tests\Feature\Walks;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Accounts\Models\RoleCapability;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Walks\Actions\SaveWalkDraft;
use App\Domain\Walks\Models\Grade;
use App\Domain\Walks\Models\Tag;
use App\Domain\Walks\Models\Walk;
use App\Filament\Resources\WalkResource\Pages\CreateWalk;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

    public function test_later_steps_and_repeated_navigation_update_the_same_draft(): void
    {
        $leader = User::factory()->walkLeader()->create();
        $grade = Grade::query()->create([
            'display_order' => 1,
            'name' => 'Moderate',
            'description' => 'Mixed paths and sustained climbs.',
        ]);
        $existingTag = Tag::query()->create(['name' => 'Woodland']);

        $component = Livewire::actingAs($leader)
            ->test(CreateWalk::class)
            ->fillForm([
                'title' => 'Reservoir circuit',
                'starts_at' => '2026-09-12 09:30:00',
            ])
            ->goToNextWizardStep();
        $draftId = $component->get('draftId');
        $walkId = Walk::query()->findOrFail($draftId)->id;
        $eventId = Walk::query()->findOrFail($draftId)->event_id;

        $component
            ->fillForm([
                'grade_id' => $grade->id,
                'tag_ids' => [$existingTag->id],
                'distance' => 8.5,
                'ascent' => 320,
                'estimated_duration_minutes' => 240,
                'capacity' => 18,
                'availability' => 'Places available',
                'terrain_notes' => 'Woodland paths and open hillside.',
            ])
            ->callFormComponentAction('tag_ids', 'createOption', ['name' => 'Riverside'])
            ->assertHasNoFormComponentActionErrors()
            ->goToWizardStep(3)
            ->assertWizardCurrentStep(3);
        $inlineTag = Tag::query()->where('name', 'Riverside')->sole();
        $draft = Walk::query()->with(['tags', 'event'])->findOrFail($draftId);

        $this->assertSame($grade->id, $draft->grade_id);
        $this->assertSame([$existingTag->id, $inlineTag->id], $draft->tags->modelKeys());
        $this->assertSame('8.50', $draft->distance);
        $this->assertSame('320.00', $draft->ascent);
        $this->assertSame(240, $draft->estimated_duration_minutes);
        $this->assertSame(18, $draft->capacity);
        $this->assertSame('Places available', $draft->availability);
        $this->assertSame('Woodland paths and open hillside.', $draft->terrain_notes);

        $component
            ->fillForm([
                'directions' => 'Meet beside the northern entrance.',
                'is_public_transport_friendly' => true,
                'public_transport_station_stop' => 'Reservoir Road',
                'parking_notes' => 'Use the signed overflow area.',
                'toilet_information' => 'Facilities at the visitor centre.',
                'kit_checklist' => ['Waterproofs', 'Lunch'],
            ])
            ->goToWizardStep(4)
            ->assertWizardCurrentStep(4);
        $draft = Walk::query()->with(['tags', 'event'])->findOrFail($draftId);

        $this->assertSame('Meet beside the northern entrance.', $draft->directions);
        $this->assertTrue($draft->is_public_transport_friendly);
        $this->assertSame('Reservoir Road', $draft->public_transport_station_stop);
        $this->assertSame('Use the signed overflow area.', $draft->parking_notes);
        $this->assertSame(['Waterproofs', 'Lunch'], $draft->kit_checklist);
        $this->assertSame('8.50', $draft->distance);
        $this->assertSame([$existingTag->id, $inlineTag->id], $draft->tags->modelKeys());

        $component
            ->fillForm([
                'summary' => 'A varied circuit around the reservoir.',
                'description' => 'A longer description for walkers.',
                'featured_image_path' => '/images/demo/reservoir.jpg',
                'attachments' => [[
                    'path' => 'walks/route-notes.pdf',
                    'name' => 'Route notes.pdf',
                    'mime_type' => 'application/pdf',
                    'size_bytes' => 1024,
                ]],
            ])
            ->goToWizardStep(5)
            ->assertWizardCurrentStep(5)
            ->goToPreviousWizardStep()
            ->fillForm(['summary' => 'Updated reservoir circuit summary.'])
            ->goToWizardStep(5)
            ->assertWizardCurrentStep(5);
        $draft = Walk::query()->with(['tags', 'event'])->findOrFail($draftId);

        $this->assertSame($walkId, $draft->id);
        $this->assertSame($eventId, $draft->event_id);
        $this->assertSame('Updated reservoir circuit summary.', $draft->event->summary);
        $this->assertSame('A longer description for walkers.', $draft->event->description);
        $this->assertSame('/images/demo/reservoir.jpg', $draft->featured_image_path);
        $this->assertSame('Route notes.pdf', $draft->attachments[0]['name']);
        $this->assertSame('8.50', $draft->distance);
        $this->assertSame('Meet beside the northern entrance.', $draft->directions);
        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseCount('walks', 1);
    }

    public function test_earlier_checkpoint_preserves_unvisited_later_step_data(): void
    {
        $leader = User::factory()->walkLeader()->create();
        $component = Livewire::actingAs($leader)
            ->test(CreateWalk::class)
            ->fillForm([
                'title' => 'Reservoir circuit',
                'starts_at' => '2026-09-12 09:30:00',
            ])
            ->goToNextWizardStep();
        $draft = Walk::query()->findOrFail($component->get('draftId'));
        app(SaveWalkDraft::class)->update($draft, $leader, [
            'summary' => 'Stored before visiting the description step.',
        ]);

        $component
            ->fillForm(['distance' => 6.75])
            ->goToWizardStep(3)
            ->assertWizardCurrentStep(3);

        $draft->event->refresh();
        $this->assertSame('Stored before visiting the description step.', $draft->event->summary);
        $this->assertSame('6.75', $draft->fresh()->distance);
        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseCount('walks', 1);
    }

    public function test_owned_draft_can_resume_at_its_saved_step_and_update_the_same_record(): void
    {
        $leader = User::factory()->walkLeader()->create();
        $coLeader = User::factory()->walkLeader()->create();
        $grade = Grade::query()->create([
            'display_order' => 1,
            'name' => 'Moderate',
            'description' => 'Mixed paths.',
        ]);
        $tag = Tag::query()->create(['name' => 'Woodland']);
        $draft = app(SaveWalkDraft::class)->create($leader, [
            'title' => 'Reservoir circuit',
            'starts_at' => '2026-09-12 09:30:00',
            'meeting_location_name' => 'North gate',
        ]);
        $draft = app(SaveWalkDraft::class)->update($draft, $leader, [
            'grade_id' => $grade->id,
            'tag_ids' => [$tag->id],
            'co_leader_ids' => [$coLeader->id],
            'distance' => 8.5,
            'directions' => 'Meet beside the visitor centre.',
            'summary' => 'A varied reservoir circuit.',
        ]);

        Livewire::actingAs($leader);
        $component = Livewire::withQueryParams([
            'draft' => $draft->id,
            'step' => 'walk-details',
        ])->test(CreateWalk::class)
            ->assertWizardCurrentStep(2)
            ->assertFormSet([
                'title' => 'Reservoir circuit',
                'meeting_location_name' => 'North gate',
                'grade_id' => $grade->id,
                'tag_ids' => [$tag->id],
                'co_leader_ids' => [$coLeader->id],
                'distance' => '8.50',
                'directions' => 'Meet beside the visitor centre.',
                'summary' => 'A varied reservoir circuit.',
                'primary_leader_id' => $leader->id,
            ])
            ->fillForm(['distance' => 9.25])
            ->goToWizardStep(3)
            ->assertWizardCurrentStep(3);

        $updated = Walk::query()->findOrFail($component->get('draftId'));
        $this->assertSame($draft->id, $updated->id);
        $this->assertSame('9.25', $updated->distance);
        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseCount('walks', 1);
    }

    public function test_missing_or_inaccessible_draft_cannot_be_resumed(): void
    {
        $owner = User::factory()->walkLeader()->create();
        $otherLeader = User::factory()->walkLeader()->create();
        $draft = app(SaveWalkDraft::class)->create($owner, [
            'title' => 'Private reservoir circuit',
            'starts_at' => '2026-09-12 09:30:00',
        ]);

        Livewire::actingAs($otherLeader);
        foreach ([$draft->id, 999999] as $draftId) {
            try {
                Livewire::withQueryParams(['draft' => $draftId])->test(CreateWalk::class);
                $this->fail('An inaccessible or missing draft rendered.');
            } catch (ModelNotFoundException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_non_draft_walk_cannot_be_resumed(): void
    {
        $leader = User::factory()->walkLeader()->create();

        foreach ([EventStatus::PendingApproval, EventStatus::Published] as $status) {
            $walk = app(SaveWalkDraft::class)->create($leader, [
                'title' => 'Private '.$status->value,
                'starts_at' => '2026-09-12 09:30:00',
            ]);
            $walk->event->forceFill(['status' => $status])->save();

            Livewire::actingAs($leader);
            Livewire::withQueryParams(['draft' => $walk->id])
                ->test(CreateWalk::class)
                ->assertNotFound();
        }
    }

    public function test_resumed_draft_re_authorises_status_and_authority_on_later_requests(): void
    {
        $leader = User::factory()->walkLeader()->create();
        $draft = app(SaveWalkDraft::class)->create($leader, [
            'title' => 'Reservoir circuit',
            'starts_at' => '2026-09-12 09:30:00',
        ]);

        Livewire::actingAs($leader);
        $statusComponent = Livewire::withQueryParams(['draft' => $draft->id])
            ->test(CreateWalk::class);
        $draft->event->forceFill(['status' => EventStatus::PendingApproval])->save();
        $statusComponent->set('data.meeting_location_name', 'Private change')->assertNotFound();

        $secondDraft = app(SaveWalkDraft::class)->create($leader, [
            'title' => 'Second reservoir circuit',
            'starts_at' => '2026-09-19 09:30:00',
        ]);
        $authorityComponent = Livewire::withQueryParams(['draft' => $secondDraft->id])
            ->test(CreateWalk::class);
        RoleCapability::query()
            ->where('role', AccountRole::WalkLeader->value)
            ->where('capability', ModuleCapability::ManageOwnWalks->value)
            ->delete();
        $authorityComponent->set('data.meeting_location_name', 'Private change')->assertForbidden();

        $this->assertNull($secondDraft->fresh()->meeting_location_name);
    }
}
