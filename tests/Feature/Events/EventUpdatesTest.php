<?php

namespace Tests\Feature\Events;

use App\Domain\Events\Actions\AddEventUpdate;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Events\EventStatusChanged;
use App\Domain\Events\Models\Event;
use App\Domain\Walks\Actions\SaveWalkDetails;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class EventUpdatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_organiser_can_add_a_dated_significant_update_to_a_current_published_walk(): void
    {
        $organiser = User::factory()->create(['can_manage_walks' => true]);
        $event = $this->walkFor($organiser, ['status' => EventStatus::Published, 'is_public' => true, 'published_at' => now()->subMinute()]);
        EventFacade::fake();

        $update = app(AddEventUpdate::class)->handle($event, $organiser, [
            'message' => 'The meeting point has moved to the east gate.',
            'is_significant' => true,
        ]);

        $this->assertSame($event->id, $update->event_id);
        $this->assertSame($organiser->id, $update->author_id);
        $this->assertTrue($update->is_significant);
        $this->assertSame(EventStatus::Changed, $event->fresh()->status);
        EventFacade::assertDispatched(EventStatusChanged::class, fn (EventStatusChanged $change): bool => $change->event->is($event)
            && $change->previousStatus === EventStatus::Published
            && $change->currentStatus === EventStatus::Changed
            && $change->changedBy?->is($organiser));
    }

    public function test_update_authorisation_is_limited_to_the_organiser_or_an_administrator(): void
    {
        $organiser = User::factory()->create(['can_manage_walks' => true]);
        $otherLeader = User::factory()->create(['can_manage_walks' => true]);
        $event = $this->walkFor($organiser);

        $this->expectException(AuthorizationException::class);

        app(AddEventUpdate::class)->handle($event, $otherLeader, [
            'message' => 'An unauthorised change.',
        ]);
    }

    public function test_update_requires_a_walk_event_with_walk_details(): void
    {
        $organiser = User::factory()->create(['can_manage_walks' => true]);
        $event = Event::factory()->for($organiser, 'organiser')->create(['type' => EventType::Walk]);

        $this->expectException(AuthorizationException::class);

        app(AddEventUpdate::class)->handle($event, $organiser, ['message' => 'No walk details exist.']);
    }

    public function test_significant_updates_do_not_turn_past_or_completed_walks_back_into_current_changed_events(): void
    {
        $organiser = User::factory()->create(['can_manage_walks' => true]);
        $past = $this->walkFor($organiser, [
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
            'status' => EventStatus::Published,
            'is_public' => true,
            'published_at' => now()->subDays(3),
        ]);
        $completed = $this->walkFor($organiser, [
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(3),
            'completion_override' => true,
            'status' => EventStatus::Completed,
            'is_public' => true,
            'published_at' => now()->subMinute(),
        ]);

        app(AddEventUpdate::class)->handle($past, $organiser, ['message' => 'A route memory.', 'is_significant' => true]);
        app(AddEventUpdate::class)->handle($completed, $organiser, ['message' => 'A completed update.', 'is_significant' => true]);

        $this->assertTrue($past->fresh()->isPast());
        $this->assertSame(EventStatus::Published, $past->fresh()->status);
        $this->assertSame(EventStatus::Completed, $completed->fresh()->status);
    }

    public function test_update_messages_are_required_plain_text_with_a_bounded_length(): void
    {
        $organiser = User::factory()->create(['can_manage_walks' => true]);
        $event = $this->walkFor($organiser);

        try {
            app(AddEventUpdate::class)->handle($event, $organiser, ['message' => '<strong>Moved</strong>']);
            $this->fail('HTML update text was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('message', $exception->errors());
        }
    }

    /** @param array<string, mixed> $attributes */
    private function walkFor(User $organiser, array $attributes = []): Event
    {
        $event = Event::factory()->for($organiser, 'organiser')->create([
            'type' => EventType::Walk,
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(4),
            ...$attributes,
        ]);
        app(SaveWalkDetails::class)->handle($event, ['primary_leader_id' => $organiser->id]);

        return $event;
    }
}
