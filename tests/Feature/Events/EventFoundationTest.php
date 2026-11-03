<?php

namespace Tests\Feature\Events;

use App\Domain\Events\Actions\ChangeEventStatus;
use App\Domain\Events\Actions\PublishEvent;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Events\EventStatusChanged;
use App\Domain\Events\Models\Event;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

final class EventFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_table_supports_the_common_event_foundation(): void
    {
        $this->assertTrue(Schema::hasColumns('events', [
            'type',
            'title',
            'slug',
            'summary',
            'description',
            'starts_at',
            'ends_at',
            'status',
            'is_public',
            'published_at',
            'completion_override',
            'organiser_id',
        ]));
    }

    public function test_event_summary_uses_text_storage_for_paragraph_sized_content(): void
    {
        $this->assertSame('text', Schema::getColumnType('events', 'summary'));
    }

    public function test_event_summary_migration_uses_medium_text_for_the_advertised_character_limit(): void
    {
        $columnType = null;
        Schema::shouldReceive('table')
            ->once()
            ->withArgs(function (string $table, callable $callback) use (&$columnType): bool {
                $blueprint = new Blueprint(DB::connection(), $table);
                $callback($blueprint);
                $columnType = $blueprint->getColumns()[0]->type ?? null;

                return $table === 'events';
            });

        $this->summaryMigration()->up();

        $this->assertSame('mediumText', $columnType);
    }

    public function test_event_summary_migration_refuses_a_lossy_rollback(): void
    {
        $event = Event::factory()->create(['summary' => str_repeat('a', 256)]);

        try {
            $this->summaryMigration()->down();
            $this->fail('The summary column was contracted despite containing data longer than 255 characters.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Cannot contract events.summary to 255 characters while longer summaries exist.',
                $exception->getMessage(),
            );
        }

        $this->assertSame(str_repeat('a', 256), $event->fresh()->summary);
    }

    public function test_event_stores_common_details_and_belongs_to_its_organiser(): void
    {
        $this->assertTrue(class_exists(Event::class));

        $organiser = User::factory()->create();

        $event = Event::factory()->for($organiser, 'organiser')->create([
            'type' => EventType::Walk,
            'title' => 'Riverside ramble',
            'slug' => 'riverside-ramble',
            'summary' => 'A relaxed riverside walk.',
            'description' => '<p>Bring waterproofs.</p>',
            'starts_at' => '2026-09-12 09:30:00',
            'ends_at' => '2026-09-12 14:30:00',
            'status' => EventStatus::Draft,
            'is_public' => false,
            'published_at' => null,
        ]);

        $this->assertSame(EventType::Walk, $event->type);
        $this->assertSame(EventStatus::Draft, $event->status);
        $this->assertFalse($event->is_public);
        $this->assertTrue($event->organiser->is($organiser));
        $this->assertSame('2026-09-12 09:30:00', $event->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-12 14:30:00', $event->ends_at->format('Y-m-d H:i:s'));
    }

    public function test_past_and_completed_events_are_inferred_from_dates_unless_explicitly_overridden(): void
    {
        $this->assertTrue(method_exists(Event::class, 'isPast'));
        $this->assertTrue(method_exists(Event::class, 'isCompleted'));

        $this->travelTo('2026-08-20 12:00:00');

        try {
            $endedEvent = Event::factory()->create([
                'starts_at' => '2026-08-20 09:00:00',
                'ends_at' => '2026-08-20 11:59:00',
            ]);
            $singleTimePastEvent = Event::factory()->create([
                'starts_at' => '2026-08-20 11:59:00',
                'ends_at' => null,
            ]);
            $futureEvent = Event::factory()->create([
                'starts_at' => '2026-08-20 12:01:00',
                'ends_at' => '2026-08-20 16:00:00',
            ]);
            $forcedPastEvent = Event::factory()->create([
                'starts_at' => '2026-08-20 12:01:00',
                'ends_at' => '2026-08-20 16:00:00',
                'completion_override' => true,
            ]);
            $keptCurrentEvent = Event::factory()->create([
                'starts_at' => '2026-08-20 09:00:00',
                'ends_at' => '2026-08-20 11:59:00',
                'completion_override' => false,
            ]);

            $this->assertTrue($endedEvent->isPast());
            $this->assertTrue($endedEvent->isCompleted());
            $this->assertTrue($singleTimePastEvent->isPast());
            $this->assertFalse($futureEvent->isPast());
            $this->assertTrue($forcedPastEvent->isPast());
            $this->assertFalse($keptCurrentEvent->isPast());

            $expectedIds = [$endedEvent->id, $singleTimePastEvent->id, $forcedPastEvent->id];

            $this->assertSame($expectedIds, Event::query()->past()->orderBy('id')->pluck('id')->all());
            $this->assertSame($expectedIds, Event::query()->completed()->orderBy('id')->pluck('id')->all());
        } finally {
            $this->travelBack();
        }
    }

    public function test_current_or_upcoming_scope_is_the_exact_inverse_of_past_at_the_completion_boundary(): void
    {
        $this->travelTo('2026-08-20 12:00:00');

        try {
            $running = Event::factory()->create([
                'starts_at' => '2026-08-19 09:00:00',
                'ends_at' => '2026-08-20 12:01:00',
            ]);
            $futureWithoutEnd = Event::factory()->create([
                'starts_at' => '2026-08-20 12:01:00',
                'ends_at' => null,
            ]);
            $forcedCurrent = Event::factory()->create([
                'starts_at' => '2026-08-18 09:00:00',
                'ends_at' => '2026-08-18 12:00:00',
                'completion_override' => false,
            ]);
            Event::factory()->create([
                'starts_at' => '2026-08-20 13:00:00',
                'ends_at' => '2026-08-20 16:00:00',
                'completion_override' => true,
            ]);
            Event::factory()->create([
                'starts_at' => '2026-08-20 09:00:00',
                'ends_at' => '2026-08-20 12:00:00',
            ]);
            Event::factory()->create([
                'starts_at' => '2026-08-20 12:00:00',
                'ends_at' => null,
            ]);

            $this->assertTrue(method_exists(Event::class, 'scopeCurrentOrUpcoming'));
            $this->assertSame(
                [$running->id, $futureWithoutEnd->id, $forcedCurrent->id],
                Event::query()->currentOrUpcoming()->orderBy('id')->pluck('id')->all(),
            );
        } finally {
            $this->travelBack();
        }
    }

    public function test_publishing_makes_an_event_public_and_dispatches_an_audit_ready_status_hook(): void
    {
        $this->assertTrue(class_exists(PublishEvent::class));
        $this->assertTrue(class_exists(EventStatusChanged::class));

        $organiser = User::factory()->create();
        $event = Event::factory()->for($organiser, 'organiser')->create();
        EventFacade::fake();
        $this->travelTo('2026-08-20 12:00:00');

        try {
            $publishedEvent = app(PublishEvent::class)->handle($event, $organiser);
        } finally {
            $this->travelBack();
        }

        $this->assertSame(EventStatus::Published, $publishedEvent->status);
        $this->assertTrue($publishedEvent->is_public);
        $this->assertSame('2026-08-20 12:00:00', $publishedEvent->published_at->format('Y-m-d H:i:s'));
        EventFacade::assertDispatched(EventStatusChanged::class, function (EventStatusChanged $change) use ($event, $organiser): bool {
            return $change->event->is($event)
                && $change->previousStatus === EventStatus::Draft
                && $change->currentStatus === EventStatus::Published
                && $change->changedBy?->is($organiser);
        });
    }

    public function test_status_change_hook_is_only_dispatched_when_the_status_actually_changes(): void
    {
        $event = Event::factory()->create([
            'status' => EventStatus::PendingApproval,
        ]);
        EventFacade::fake();

        app(ChangeEventStatus::class)->handle($event, EventStatus::PendingApproval);

        EventFacade::assertNotDispatched(EventStatusChanged::class);
    }

    public function test_publishing_an_already_published_event_persists_missing_publication_metadata_without_a_duplicate_hook(): void
    {
        $event = Event::factory()->create([
            'status' => EventStatus::Published,
            'is_public' => false,
            'published_at' => null,
        ]);
        EventFacade::fake();
        $this->travelTo('2026-08-20 12:00:00');

        try {
            app(PublishEvent::class)->handle($event);
        } finally {
            $this->travelBack();
        }

        $event->refresh();

        $this->assertTrue($event->is_public);
        $this->assertSame('2026-08-20 12:00:00', $event->published_at->format('Y-m-d H:i:s'));
        EventFacade::assertNotDispatched(EventStatusChanged::class);
    }

    private function summaryMigration(): object
    {
        return require database_path('migrations/2026_09_08_090000_expand_event_summary_to_text.php');
    }
}
