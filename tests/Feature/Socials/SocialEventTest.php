<?php

namespace Tests\Feature\Socials;

use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Socials\Actions\CreateSocial;
use App\Domain\Socials\Actions\SaveSocialDetails;
use App\Domain\Socials\Models\Social;
use App\Filament\Resources\SocialResource\Pages\CreateSocial as CreateSocialPage;
use App\Models\User;
use App\Policies\SocialPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class SocialEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_social_extension_has_only_social_specific_fields_while_common_state_remains_on_events(): void
    {
        $this->assertTrue(Schema::hasColumns('socials', [
            'event_id',
            'venue_name',
            'venue_address',
            'cost',
            'booking_status',
            'booking_instructions',
            'booking_url',
            'contact_name',
            'contact_details',
            'capacity',
            'availability',
            'accessibility_notes',
            'transport_notes',
            'attachments',
        ]));
        $this->assertFalse(Schema::hasColumn('socials', 'title'));
        $this->assertFalse(Schema::hasColumn('socials', 'status'));
    }

    public function test_social_details_persist_supported_optional_information(): void
    {
        $event = Event::factory()->create(['type' => EventType::Social]);

        $social = app(SaveSocialDetails::class)->handle($event, [
            'venue_name' => 'Community Hall',
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
            'attachments' => [[
                'path' => 'socials/attachments/menu.pdf',
                'name' => 'Menu.pdf',
            ]],
        ]);

        $this->assertSame($event->id, $social->event_id);
        $this->assertSame('Community Hall', $social->venue_name);
        $this->assertSame(40, $social->capacity);
        $this->assertSame('Menu.pdf', $social->attachments[0]['name']);
    }

    public function test_social_details_reject_non_social_events(): void
    {
        $this->expectException(ValidationException::class);

        app(SaveSocialDetails::class)->handle(Event::factory()->create(), []);
    }

    public function test_social_details_reject_unsafe_attachment_names(): void
    {
        $this->expectException(ValidationException::class);

        app(SaveSocialDetails::class)->handle(Event::factory()->create(['type' => EventType::Social]), [
            'attachments' => [['path' => 'socials/attachments/file.pdf', 'name' => "bad\r\nname.pdf"]],
        ]);
    }

    public function test_administrator_can_create_a_draft_social_with_common_event_identity(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);

        $social = app(CreateSocial::class)->handle($administrator, [
            'title' => 'Community supper',
            'slug' => 'community-supper',
            'starts_at' => '2026-09-12 19:00:00',
            'ends_at' => '2026-09-12 22:00:00',
            'venue_name' => 'Market Hall',
        ]);

        $this->assertSame(EventType::Social, $social->event->type);
        $this->assertSame('draft', $social->event->status->value);
        $this->assertFalse($social->event->is_public);
        $this->assertSame($administrator->id, $social->event->organiser_id);
        $this->assertSame('Market Hall', $social->venue_name);
    }

    public function test_only_administrators_can_manage_socials_in_phase_four(): void
    {
        $social = Social::query()->create([
            'event_id' => Event::factory()->create(['type' => EventType::Social])->id,
        ]);
        $administrator = User::factory()->create(['is_admin' => true]);
        $walkLeader = User::factory()->create(['can_manage_walks' => true]);

        $policy = new SocialPolicy;

        $this->assertTrue($policy->create($administrator));
        $this->assertTrue($policy->update($administrator, $social));
        $this->assertFalse($policy->create($walkLeader));
        $this->assertFalse($policy->update($walkLeader, $social));
    }

    public function test_administrator_can_create_a_social_through_filament(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);
        $this->actingAs($administrator);

        Livewire::test(CreateSocialPage::class)
            ->fillForm([
                'title' => 'Autumn supper',
                'slug' => 'autumn-supper',
                'starts_at' => '2026-10-17 19:00:00',
                'venue_name' => 'Market Hall',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $event = Event::query()->where('slug', 'autumn-supper')->firstOrFail();
        $this->assertSame(EventType::Social, $event->type);
        $this->assertSame('Market Hall', $event->social->venue_name);
    }
}
