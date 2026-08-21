<?php

namespace Tests\Feature\Events;

use App\Domain\Events\Actions\CreateRecurringSeries;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\RecurringSeries;
use App\Domain\Socials\Actions\SaveSocialDetails;
use App\Filament\Resources\RecurringSeriesResource\Pages\CreateRecurringSeries as CreateRecurringSeriesPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class RecurringSeriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_recurring_series_schema_links_concrete_event_occurrences(): void
    {
        $this->assertTrue(Schema::hasColumns('recurring_series', ['source_event_id', 'frequency', 'interval', 'occurrence_count']));
        $this->assertTrue(Schema::hasColumns('events', ['recurring_series_id', 'occurrence_number']));
    }

    public function test_every_n_weeks_generates_separate_concrete_occurrences(): void
    {
        $source = $this->social('Fortnightly supper', '2026-09-03 19:00:00', '2026-09-03 22:00:00');

        $series = app(CreateRecurringSeries::class)->handle($source, frequency: 'weeks', interval: 2, occurrenceCount: 3);
        $occurrences = $series->occurrences()->orderBy('occurrence_number')->get();

        $this->assertCount(3, $occurrences);
        $this->assertSame([1, 2, 3], $occurrences->pluck('occurrence_number')->all());
        $this->assertSame(['2026-09-03', '2026-09-17', '2026-10-01'], $occurrences->map(fn (Event $event) => $event->starts_at->format('Y-m-d'))->all());
        $this->assertCount(3, $occurrences->pluck('id')->unique());
        $this->assertCount(3, $occurrences->pluck('slug')->unique());
        $this->assertCount(3, $occurrences->pluck('calendar_uid')->unique());
        $this->assertSame(['Community Hall', 'Community Hall', 'Community Hall'], $occurrences->map(fn (Event $event) => $event->social->venue_name)->all());
    }

    public function test_monthly_generation_uses_calendar_months_without_date_overflow(): void
    {
        $source = $this->social('Month end social', '2027-01-31 19:00:00', '2027-01-31 22:00:00');

        $series = app(CreateRecurringSeries::class)->handle($source, frequency: 'months', interval: 1, occurrenceCount: 3);

        $this->assertSame(
            ['2027-01-31', '2027-02-28', '2027-03-31'],
            $series->occurrences()->orderBy('occurrence_number')->get()->map(fn (Event $event) => $event->starts_at->format('Y-m-d'))->all(),
        );
    }

    public function test_editing_or_cancelling_one_occurrence_does_not_change_its_siblings(): void
    {
        $source = $this->social('Weekly supper', '2026-09-03 19:00:00', '2026-09-03 22:00:00');
        $series = app(CreateRecurringSeries::class)->handle($source, frequency: 'weeks', interval: 1, occurrenceCount: 3);
        $second = $series->occurrences()->where('occurrence_number', 2)->firstOrFail();
        $second->update(['title' => 'Moved supper', 'status' => EventStatus::Cancelled]);

        $occurrences = $series->occurrences()->orderBy('occurrence_number')->get();
        $this->assertSame(['Weekly supper', 'Moved supper', 'Weekly supper'], $occurrences->pluck('title')->all());
        $this->assertSame([EventStatus::Draft, EventStatus::Cancelled, EventStatus::Draft], $occurrences->pluck('status')->all());
    }

    #[DataProvider('invalidDefinitions')]
    public function test_unsupported_or_unbounded_recurrence_definitions_are_rejected(string $frequency, int $interval, int $occurrenceCount): void
    {
        $this->expectException(ValidationException::class);
        app(CreateRecurringSeries::class)->handle($this->social('Invalid series', '2026-09-03 19:00:00', '2026-09-03 22:00:00'), $frequency, $interval, $occurrenceCount);
    }

    public function test_administrator_can_define_and_generate_a_series_through_filament(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);
        $source = $this->social('Admin series', '2026-09-03 19:00:00', '2026-09-03 22:00:00');
        $this->actingAs($administrator);

        Livewire::test(CreateRecurringSeriesPage::class)
            ->fillForm(['source_event_id' => $source->id, 'frequency' => 'weeks', 'interval' => 2, 'occurrence_count' => 3])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(3, RecurringSeries::query()->firstOrFail()->occurrences()->count());
    }

    /** @return array<string, array{string, int, int}> */
    public static function invalidDefinitions(): array
    {
        return [
            'daily unsupported' => ['days', 1, 3],
            'zero interval' => ['weeks', 0, 3],
            'single occurrence' => ['months', 1, 1],
            'unbounded count' => ['weeks', 1, 101],
        ];
    }

    private function social(string $title, string $startsAt, string $endsAt): Event
    {
        $event = Event::factory()->create([
            'type' => EventType::Social,
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => EventStatus::Draft,
        ]);
        app(SaveSocialDetails::class)->handle($event, ['venue_name' => 'Community Hall']);

        return $event->refresh();
    }
}
