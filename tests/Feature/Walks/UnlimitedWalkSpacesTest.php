<?php

namespace Tests\Feature\Walks;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Walks\Actions\SaveWalkDetails;
use App\Domain\Walks\Models\Walk;
use App\Filament\Resources\WalkResource\Pages\CreateWalk;
use App\Filament\Resources\WalkResource\Pages\EditWalk;
use App\Models\User;
use App\ViewModels\PublicWalkCardViewModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class UnlimitedWalkSpacesTest extends TestCase
{
    use RefreshDatabase;

    public function test_unlimited_spaces_can_be_saved_in_the_walk_wizard(): void
    {
        $leader = User::factory()->walkLeader()->create();
        $component = Livewire::actingAs($leader)->test(CreateWalk::class)
            ->fillForm(['title' => 'Open walk', 'starts_at' => now()->addWeek()->format('Y-m-d H:i:s')])
            ->goToNextWizardStep()
            ->assertWizardCurrentStep(2)
            ->set('data.unlimited_spaces', true)
            ->assertSet('data.capacity', 0)
            ->goToWizardStep(3)->assertHasNoFormErrors()->assertWizardCurrentStep(3);
        $this->assertSame(0, Walk::query()->findOrFail($component->get('draftId'))->capacity);
    }

    public function test_unlimited_spaces_are_shown_once_on_a_walk_card(): void
    {
        $leader = User::factory()->walkLeader()->create();
        $event = Event::factory()->create(['organiser_id' => $leader->id]);
        app(SaveWalkDetails::class)->handle($event, ['primary_leader_id' => $leader->id, 'capacity' => 0, 'availability' => 'Unlimited Spaces']);
        $card = PublicWalkCardViewModel::fromEvent($event->fresh(), new SiteProfile);
        $view = $this->blade('<x-public.event-card :event="$event" />', ['event' => $card]);
        $this->assertSame(1, substr_count(strtolower((string) $view), 'unlimited spaces'));
    }

    public function test_existing_walk_can_switch_between_limited_and_unlimited_spaces(): void
    {
        $leader = User::factory()->walkLeader()->create();
        $event = Event::factory()->create(['organiser_id' => $leader->id, 'status' => EventStatus::Published, 'is_public' => true, 'published_at' => now()->subMinute()]);
        $walk = app(SaveWalkDetails::class)->handle($event, ['primary_leader_id' => $leader->id, 'capacity' => 12]);
        Livewire::actingAs($leader)->test(EditWalk::class, ['record' => $walk->id])
            ->assertSet('data.unlimited_spaces', false)
            ->set('data.unlimited_spaces', true)->call('save')->assertHasNoFormErrors();
        $this->assertSame(0, $walk->refresh()->capacity);
        $this->get(route('walks.show', $event->slug))->assertOk()->assertSee('Unlimited spaces');
        $this->get(route('walks.index'))->assertOk()->assertSee('Unlimited spaces');
        Livewire::test(EditWalk::class, ['record' => $walk->id])
            ->assertSet('data.unlimited_spaces', true)
            ->set('data.unlimited_spaces', false)->set('data.capacity', 18)
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame(18, $walk->refresh()->capacity);
        $this->get(route('walks.show', $event->slug))->assertOk()->assertSee('18 walkers')->assertDontSee('Unlimited spaces');
    }
}
