<?php

namespace Tests\Feature\Public;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Queries\PublicEventsQuery;
use App\Domain\Holidays\Actions\AssignHolidayChild;
use App\Domain\Holidays\Actions\SaveHolidayDetails;
use App\Domain\Socials\Actions\SaveSocialDetails;
use App\Domain\Walks\Models\Walk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WhatsOnTest extends TestCase
{
    use RefreshDatabase;

    public function test_authoritative_public_query_includes_only_visible_published_upcoming_event_state(): void
    {
        $walk = $this->event(EventType::Walk, 'Public walk', '2026-10-03 09:00:00');
        $social = $this->event(EventType::Social, 'Changed social', '2026-10-04 19:00:00', EventStatus::Changed);
        $holiday = $this->event(EventType::Holiday, 'Cancelled holiday', '2026-10-09 16:00:00', EventStatus::Cancelled);
        $this->event(EventType::Social, 'Draft social', '2026-10-05 19:00:00', EventStatus::Draft, false);
        $this->event(EventType::Walk, 'Past walk', '2026-07-03 09:00:00');

        $events = app(PublicEventsQuery::class)->upcoming()->get();

        $this->assertSame([$walk->id, $social->id, $holiday->id], $events->pluck('id')->all());
    }

    public function test_current_holiday_and_walk_and_social_queries_share_completion_semantics(): void
    {
        $this->travelTo('2026-10-04 12:00:00');

        try {
            $holiday = $this->event(EventType::Holiday, 'Current shared holiday', '2026-10-02 16:00:00');
            $holiday->update(['ends_at' => '2026-10-05 10:00:00']);
            $walk = $this->event(EventType::Walk, 'Current shared walk', '2026-10-04 09:00:00');
            $walk->update(['ends_at' => '2026-10-04 15:00:00']);
            $social = $this->event(EventType::Social, 'Current shared social', '2026-10-04 11:00:00');
            $social->update(['ends_at' => '2026-10-04 14:00:00']);

            $this->get('/whats-on')
                ->assertOk()
                ->assertSee($holiday->title)
                ->assertSee($walk->title)
                ->assertSee($social->title);
            $this->get('/walks')->assertOk()->assertSee($walk->title);
            $this->get('/socials')->assertOk()->assertSee($social->title);

            $this->travelTo('2026-10-05 10:00:00');
            $this->get('/whats-on')->assertOk()->assertDontSee($holiday->title);
        } finally {
            $this->travelBack();
        }
    }

    public function test_completion_overrides_force_public_query_inclusion_and_exclusion(): void
    {
        $this->travelTo('2026-10-04 12:00:00');

        try {
            $keptCurrent = $this->event(EventType::Social, 'Override current social', '2026-10-01 19:00:00');
            $keptCurrent->update(['ends_at' => '2026-10-01 22:00:00', 'completion_override' => false]);
            $forcedPast = $this->event(EventType::Walk, 'Override completed walk', '2026-10-10 09:00:00');
            $forcedPast->update(['completion_override' => true]);

            $events = app(PublicEventsQuery::class)->upcoming()->get();

            $this->assertTrue($events->contains($keptCurrent));
            $this->assertFalse($events->contains($forcedPast));
        } finally {
            $this->travelBack();
        }
    }

    public function test_combined_list_is_chronological_and_type_filter_is_dedicated(): void
    {
        $this->event(EventType::Holiday, 'October holiday', '2026-10-09 16:00:00');
        $this->event(EventType::Social, 'October social', '2026-10-04 19:00:00');
        $this->event(EventType::Walk, 'October walk', '2026-10-03 09:00:00');

        $this->get('/whats-on')
            ->assertOk()
            ->assertSeeInOrder(['October walk', 'October social', 'October holiday'])
            ->assertSee('/whats-on?type=walk', false)
            ->assertSee('/whats-on?type=social', false)
            ->assertSee('/whats-on?type=holiday', false);

        $this->get('/whats-on?type=social')
            ->assertOk()->assertSee('October social')->assertDontSee('October walk')->assertDontSee('October holiday');
    }

    public function test_month_calendar_and_list_fallback_use_the_same_authoritative_month_state(): void
    {
        $this->event(EventType::Walk, 'October walk', '2026-10-03 09:00:00');
        $this->event(EventType::Social, 'October social', '2026-10-04 19:00:00');
        $this->event(EventType::Holiday, 'November holiday', '2026-11-06 16:00:00');

        $response = $this->get('/whats-on/calendar?month=2026-10')
            ->assertOk()
            ->assertSee('October 2026')
            ->assertSee('October walk')
            ->assertSee('October social')
            ->assertDontSee('November holiday')
            ->assertSee('<table', false)
            ->assertSee('<caption', false)
            ->assertSee('List view')
            ->assertSee('Previous month')
            ->assertSee('Next month');

        $response->assertSee('/whats-on?month=2026-10', false);
    }

    public function test_holiday_setting_can_hide_children_from_global_views_without_removing_their_public_identity(): void
    {
        $holiday = $this->event(EventType::Holiday, 'Private itinerary calendar', '2026-10-02 16:00:00');
        $holiday->update(['ends_at' => '2026-10-05 10:00:00']);
        $holiday->holiday->update(['show_child_events_in_global_calendar' => false]);
        $child = $this->event(EventType::Social, 'Trip supper', '2026-10-03 19:00:00');
        $child->update(['ends_at' => '2026-10-03 22:00:00']);
        app(AssignHolidayChild::class)->handle($holiday->refresh(), $child->refresh());

        $this->get('/whats-on')->assertOk()->assertSee('Private itinerary calendar')->assertDontSee('Trip supper');
        $this->get('/whats-on/calendar?month=2026-10')->assertOk()->assertDontSee('Trip supper');
        $this->get('/socials/'.$child->slug)->assertOk()->assertSee('Trip supper');
    }

    public function test_calendar_event_links_are_native_keyboard_focusable_links_and_list_is_primary_fallback(): void
    {
        $this->event(EventType::Social, 'Keyboard social', '2026-10-04 19:00:00');

        $this->get('/whats-on/calendar?month=2026-10')
            ->assertOk()
            ->assertSee('aria-label="Calendar navigation"', false)
            ->assertSee('href="http://localhost/socials/keyboard-social"', false)
            ->assertSee('View October events as a list');
    }

    public function test_exceptional_lifecycle_state_overrides_availability_on_cards_details_and_calendar(): void
    {
        $social = $this->event(EventType::Social, 'Cancelled community supper', '2026-10-04 19:00:00', EventStatus::Cancelled);
        $social->social->update(['availability' => 'Places available', 'booking_status' => 'Booking open']);

        $this->get('/whats-on')->assertOk()->assertSee('Cancelled')->assertDontSee('Places available');
        $this->get('/whats-on/calendar?month=2026-10')->assertOk()->assertSee('Cancelled');
        $this->get('/socials/'.$social->slug)
            ->assertOk()
            ->assertSee('Cancelled')
            ->assertDontSee('Places available')
            ->assertDontSee('Booking open');
    }

    public function test_multi_day_event_spanning_into_month_is_present_on_each_applicable_calendar_day(): void
    {
        $holiday = $this->event(EventType::Holiday, 'Month-spanning holiday', '2026-09-30 16:00:00');
        $holiday->update(['ends_at' => '2026-10-02 10:00:00']);

        $response = $this->get('/whats-on/calendar?month=2026-10')->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), 'Month-spanning holiday'));
    }

    private function event(EventType $type, string $title, string $startsAt, EventStatus $status = EventStatus::Published, bool $public = true): Event
    {
        $start = now()->parse($startsAt);
        $event = Event::factory()->create([
            'type' => $type, 'title' => $title, 'slug' => str($title)->slug()->toString(),
            'starts_at' => $start, 'ends_at' => $start->copy()->addHours(3),
            'status' => $status, 'is_public' => $public,
            'published_at' => $public && $status !== EventStatus::Draft ? now() : null,
        ]);
        match ($type) {
            EventType::Walk => Walk::query()->create(['event_id' => $event->id, 'primary_leader_id' => $event->organiser_id]),
            EventType::Social => app(SaveSocialDetails::class)->handle($event, []),
            EventType::Holiday => app(SaveHolidayDetails::class)->handle($event, []),
        };

        return $event->refresh();
    }
}
