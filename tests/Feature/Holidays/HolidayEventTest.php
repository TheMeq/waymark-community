<?php

namespace Tests\Feature\Holidays;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Holidays\Actions\CreateHoliday;
use App\Domain\Holidays\Actions\SaveHolidayDetails;
use App\Filament\Resources\HolidayResource\Pages\CreateHoliday as CreateHolidayPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class HolidayEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_holiday_extension_keeps_common_identity_on_events(): void
    {
        $this->assertTrue(Schema::hasColumns('holidays', [
            'event_id', 'destination', 'accommodation', 'pricing_type', 'price_amount',
            'currency', 'deposit_amount', 'pricing_notes', 'capacity', 'availability',
            'booking_deadline', 'booking_status', 'booking_instructions', 'booking_url',
            'booking_contact', 'travel_details', 'itinerary_notes', 'featured_image_path',
            'attachments',
        ]));
        $this->assertFalse(Schema::hasColumn('holidays', 'title'));
        $this->assertFalse(Schema::hasColumn('holidays', 'starts_at'));
    }

    public function test_holiday_details_persist_the_approved_optional_information(): void
    {
        $event = Event::factory()->create(['type' => EventType::Holiday]);

        $holiday = app(SaveHolidayDetails::class)->handle($event, [
            'destination' => 'Northumberland Coast',
            'accommodation' => 'Seaview Lodge',
            'pricing_type' => 'from',
            'price_amount' => '325.00',
            'currency' => 'GBP',
            'deposit_amount' => '75.00',
            'pricing_notes' => 'Final balance due six weeks before travel.',
            'capacity' => 24,
            'availability' => 'Places available',
            'booking_deadline' => '2026-09-01 17:00:00',
            'booking_status' => 'Booking open',
            'booking_instructions' => 'Contact the accommodation directly.',
            'booking_url' => 'https://example.com/holiday',
            'booking_contact' => 'trips@example.test',
            'travel_details' => 'Rail connections are available.',
            'itinerary_notes' => 'Friday arrival and welcome meal.',
            'featured_image_path' => '/images/demo/coastal-weekend.png',
            'attachments' => [['path' => 'holidays/attachments/packing.pdf', 'name' => 'Packing list.pdf']],
        ]);

        $this->assertSame('Northumberland Coast', $holiday->destination);
        $this->assertSame('from', $holiday->pricing_type);
        $this->assertSame('325.00', $holiday->price_amount);
        $this->assertSame('GBP', $holiday->currency);
        $this->assertSame('Packing list.pdf', $holiday->attachments[0]['name']);
    }

    public function test_holiday_details_reject_a_non_holiday_event(): void
    {
        $this->expectException(ValidationException::class);
        app(SaveHolidayDetails::class)->handle(Event::factory()->create(), []);
    }

    public function test_fixed_or_from_pricing_requires_an_amount_and_currency(): void
    {
        $this->expectException(ValidationException::class);
        app(SaveHolidayDetails::class)->handle(Event::factory()->create(['type' => EventType::Holiday]), [
            'pricing_type' => 'fixed',
        ]);
    }

    public function test_administrator_creates_a_draft_holiday_without_booking_or_payment_records(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);

        $holiday = app(CreateHoliday::class)->handle($administrator, [
            'title' => 'Coast and moor weekend',
            'slug' => 'coast-and-moor-weekend',
            'starts_at' => '2026-10-02 16:00:00',
            'ends_at' => '2026-10-05 10:00:00',
            'destination' => 'Northumberland',
        ]);

        $this->assertSame(EventType::Holiday, $holiday->event->type);
        $this->assertSame(EventStatus::Draft, $holiday->event->status);
        $this->assertFalse($holiday->event->is_public);
        $this->assertFalse(Schema::hasTable('holiday_attendees'));
        $this->assertFalse(Schema::hasTable('holiday_payments'));
        $this->assertFalse(Schema::hasTable('holiday_bookings'));
    }

    public function test_administrator_can_create_a_holiday_through_filament(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);
        $this->actingAs($administrator);

        Livewire::test(CreateHolidayPage::class)
            ->fillForm([
                'title' => 'Autumn coast weekend',
                'slug' => 'autumn-coast-weekend',
                'starts_at' => '2026-10-02 16:00:00',
                'ends_at' => '2026-10-05 10:00:00',
                'destination' => 'Northumberland',
                'pricing_type' => 'tbc',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $event = Event::query()->where('slug', 'autumn-coast-weekend')->firstOrFail();
        $this->assertSame(EventType::Holiday, $event->type);
        $this->assertSame('Northumberland', $event->holiday->destination);
    }
}
