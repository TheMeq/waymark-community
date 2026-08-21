<?php

namespace Tests\Feature\Public;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Holidays\Actions\AssignHolidayChild;
use App\Domain\Holidays\Actions\SaveHolidayDetails;
use App\Domain\Socials\Actions\SaveSocialDetails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PublicHolidayPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_holiday_listing_contains_only_upcoming_published_holidays(): void
    {
        $earlier = $this->publishedHoliday('Coast weekend', '+2 weeks');
        $later = $this->publishedHoliday('Mountain weekend', '+1 month');
        $this->publishedHoliday('Draft trip', '+3 weeks', ['status' => EventStatus::Draft, 'published_at' => null]);

        $this->get('/weekends')
            ->assertOk()
            ->assertSeeInOrder([$earlier->title, $later->title])
            ->assertDontSee('Draft trip');
    }

    public function test_holiday_detail_renders_structured_external_booking_information_without_an_internal_booking_form(): void
    {
        $event = $this->publishedHoliday('Coast and moor weekend', '+2 weeks', [], [
            'destination' => 'Northumberland Coast',
            'accommodation' => 'Seaview Lodge',
            'pricing_type' => 'from',
            'price_amount' => '325.00',
            'currency' => 'GBP',
            'deposit_amount' => '75.00',
            'availability' => 'Six places left',
            'booking_status' => 'Booking open',
            'booking_deadline' => now()->addWeek(),
            'booking_instructions' => 'Contact the accommodation directly.',
            'booking_url' => 'https://example.com/holiday',
            'booking_contact' => 'trips@example.test',
            'travel_details' => 'Rail connections are available.',
            'itinerary_notes' => 'Friday arrival and welcome meal.',
            'featured_image_path' => '/images/demo/coastal-weekend.png',
        ]);

        $this->get('/weekends/'.$event->slug)
            ->assertOk()
            ->assertSee('Northumberland Coast')
            ->assertSee('Seaview Lodge')
            ->assertSee('From £325.00')
            ->assertSee('Deposit £75.00')
            ->assertSee('External booking information')
            ->assertSee('https://example.com/holiday', false)
            ->assertSee('/images/demo/coastal-weekend.png', false)
            ->assertDontSee('<form', false)
            ->assertDontSee('RSVP');
    }

    public function test_empty_optional_holiday_sections_are_omitted_cleanly(): void
    {
        $event = $this->publishedHoliday('Simple weekend', '+2 weeks');

        $this->get('/weekends/'.$event->slug)
            ->assertOk()
            ->assertDontSee('>Booking</h2>', false)
            ->assertDontSee('>Travel</h2>', false)
            ->assertDontSee('>Itinerary</h2>', false)
            ->assertDontSee('<img', false);
    }

    public function test_homepage_spotlight_uses_the_next_published_holiday(): void
    {
        $next = $this->publishedHoliday('Real coast weekend', '+2 weeks', [], [
            'destination' => 'The coast',
            'featured_image_path' => '/images/demo/coastal-weekend.png',
        ]);
        $this->publishedHoliday('Later mountain weekend', '+1 month');

        $this->get('/')
            ->assertOk()
            ->assertSee($next->title)
            ->assertSee('/weekends/'.$next->slug, false)
            ->assertDontSee('Later mountain weekend');
    }

    public function test_holiday_detail_exposes_only_verified_limited_attachments(): void
    {
        Storage::fake('local');
        config()->set('holidays.attachments.disk', 'local');
        Storage::disk('local')->put('holidays/attachments/packing.pdf', 'packing');
        $event = $this->publishedHoliday('Attached weekend', '+2 weeks', [], [
            'attachments' => [
                ['path' => 'holidays/attachments/packing.pdf', 'name' => 'Packing list.pdf'],
                ['path' => 'holidays/attachments/missing.pdf', 'name' => 'Missing.pdf'],
            ],
        ]);

        $this->get('/weekends/'.$event->slug)
            ->assertOk()
            ->assertSee('Packing list.pdf')
            ->assertDontSee('Missing.pdf');
        $this->get('/weekends/'.$event->slug.'/attachments/0')->assertOk();
        $this->get('/weekends/'.$event->slug.'/attachments/1')->assertNotFound();
    }

    public function test_holiday_detail_itinerary_links_published_child_walks_and_socials_by_their_own_identity(): void
    {
        $holiday = $this->publishedHoliday('Itinerary weekend', '+2 weeks');
        $walk = Event::factory()->create([
            'type' => EventType::Walk, 'title' => 'Saturday coast walk', 'slug' => 'saturday-coast-walk',
            'starts_at' => $holiday->starts_at->copy()->addDay(), 'ends_at' => $holiday->starts_at->copy()->addDay()->addHours(5),
            'status' => EventStatus::Published, 'is_public' => true, 'published_at' => now(),
        ]);
        $social = Event::factory()->create([
            'type' => EventType::Social, 'title' => 'Saturday supper', 'slug' => 'saturday-supper',
            'starts_at' => $holiday->starts_at->copy()->addDay()->addHours(7), 'ends_at' => $holiday->starts_at->copy()->addDay()->addHours(10),
            'status' => EventStatus::Published, 'is_public' => true, 'published_at' => now(),
        ]);
        app(SaveSocialDetails::class)->handle($social, []);
        app(AssignHolidayChild::class)->handle($holiday, $walk);
        app(AssignHolidayChild::class)->handle($holiday, $social);

        $this->get('/weekends/'.$holiday->slug)
            ->assertOk()
            ->assertSeeInOrder(['Saturday coast walk', 'Saturday supper'])
            ->assertSee('/walks/saturday-coast-walk', false)
            ->assertSee('/socials/saturday-supper', false);
    }

    /** @param array<string, mixed> $eventAttributes @param array<string, mixed> $holidayAttributes */
    private function publishedHoliday(string $title, string $startsAt, array $eventAttributes = [], array $holidayAttributes = []): Event
    {
        $start = now()->modify($startsAt);
        $event = Event::factory()->create([
            'type' => EventType::Holiday,
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addDays(3),
            'status' => EventStatus::Published,
            'is_public' => true,
            'published_at' => now(),
            ...$eventAttributes,
        ]);
        app(SaveHolidayDetails::class)->handle($event, $holidayAttributes);

        return $event->refresh();
    }
}
