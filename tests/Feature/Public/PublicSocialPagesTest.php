<?php

namespace Tests\Feature\Public;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Socials\Actions\SaveSocialDetails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PublicSocialPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_social_listing_contains_only_upcoming_public_socials_in_chronological_order(): void
    {
        $later = $this->publishedSocial('Later supper', '+2 weeks');
        $earlier = $this->publishedSocial('Earlier coffee evening', '+1 week');
        $this->publishedSocial('Draft social', '+3 weeks', ['status' => EventStatus::Draft, 'published_at' => null]);
        Event::factory()->create([
            'title' => 'A walk, not a social',
            'status' => EventStatus::Published,
            'is_public' => true,
            'published_at' => now(),
        ]);

        $this->get('/socials')
            ->assertOk()
            ->assertSeeInOrder([$earlier->title, $later->title])
            ->assertDontSee('Draft social')
            ->assertDontSee('A walk, not a social');
    }

    public function test_social_detail_renders_supported_information_and_a_verified_attachment(): void
    {
        Storage::fake('local');
        config()->set('socials.attachments.disk', 'local');
        Storage::disk('local')->put('socials/attachments/menu.pdf', 'menu');
        $event = $this->publishedSocial('Community supper', '+1 week', [], [
            'venue_name' => 'Market Hall',
            'venue_address' => '1 Market Square',
            'cost' => '£8 per person',
            'booking_status' => 'Booking open',
            'booking_instructions' => 'Book directly with the organiser.',
            'booking_url' => 'https://example.com/social',
            'contact_name' => 'Alex Morgan',
            'contact_details' => 'events@example.test',
            'capacity' => 40,
            'availability' => 'Places available',
            'accessibility_notes' => 'Step-free entrance.',
            'transport_notes' => 'Five minutes from the station.',
            'attachments' => [['path' => 'socials/attachments/menu.pdf', 'name' => 'Menu.pdf']],
        ]);

        $response = $this->get('/socials/'.$event->slug)
            ->assertOk()
            ->assertSee('Market Hall')
            ->assertSee('£8 per person')
            ->assertSee('Booking open')
            ->assertSee('Alex Morgan')
            ->assertSee('Places available')
            ->assertSee('Step-free entrance.')
            ->assertSee('Five minutes from the station.')
            ->assertSee('Menu.pdf');

        $response->assertSee(route('socials.attachment', [$event->slug, 0]), false);
        $this->get('/socials/'.$event->slug.'/attachments/0')->assertOk();
    }

    public function test_empty_optional_social_fields_omit_their_sections_cleanly(): void
    {
        $event = $this->publishedSocial('Simple meetup', '+1 week');

        $this->get('/socials/'.$event->slug)
            ->assertOk()
            ->assertSee('Simple meetup')
            ->assertDontSee('Booking details')
            ->assertDontSee('>Accessibility</h2>', false)
            ->assertDontSee('Getting there')
            ->assertDontSee('Downloads');
    }

    /**
     * @param  array<string, mixed>  $eventAttributes
     * @param  array<string, mixed>  $socialAttributes
     */
    private function publishedSocial(string $title, string $startsAt, array $eventAttributes = [], array $socialAttributes = []): Event
    {
        $event = Event::factory()->create([
            'type' => EventType::Social,
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'starts_at' => now()->modify($startsAt),
            'ends_at' => now()->modify($startsAt)->addHours(3),
            'status' => EventStatus::Published,
            'is_public' => true,
            'published_at' => now(),
            ...$eventAttributes,
        ]);

        app(SaveSocialDetails::class)->handle($event, $socialAttributes);

        return $event->refresh();
    }
}
