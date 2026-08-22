<?php

namespace Tests\Feature\Holidays;

use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Holidays\Actions\AssignHolidayChild;
use App\Domain\Holidays\Actions\RemoveHolidayChild;
use App\Domain\Holidays\Actions\SaveHolidayDetails;
use App\Domain\Holidays\Actions\UpdateHoliday;
use App\Domain\Holidays\Data\HolidayGallerySource;
use App\Domain\Socials\Actions\SaveSocialDetails;
use App\Filament\Resources\HolidayResource\Pages\EditHoliday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class HolidayChildEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_parent_relationship_and_holiday_calendar_setting_are_persisted(): void
    {
        $this->assertTrue(Schema::hasColumn('events', 'parent_event_id'));
        $this->assertTrue(Schema::hasColumn('holidays', 'show_child_events_in_global_calendar'));

        $parent = $this->holiday();
        $child = $this->child(EventType::Social, '2026-10-03 19:00:00', '2026-10-03 22:00:00');
        app(AssignHolidayChild::class)->handle($parent, $child);

        $this->assertTrue($child->fresh()->parent->is($parent));
        $this->assertTrue($parent->children()->firstOrFail()->is($child));
        $this->assertTrue($parent->holiday->show_child_events_in_global_calendar);
    }

    public function test_only_walk_or_social_events_can_be_holiday_children(): void
    {
        $this->expectException(ValidationException::class);
        app(AssignHolidayChild::class)->handle($this->holiday(), $this->holiday('Another holiday'));
    }

    public function test_parent_must_be_a_holiday(): void
    {
        $this->expectException(ValidationException::class);
        app(AssignHolidayChild::class)->handle($this->child(EventType::Walk, '2026-10-02 16:00:00', '2026-10-05 10:00:00'), $this->child(EventType::Social, '2026-10-03 19:00:00', '2026-10-03 22:00:00'));
    }

    #[DataProvider('outsideHolidayDates')]
    public function test_child_events_must_fit_entirely_within_the_holiday_dates(string $start, string $end): void
    {
        $this->expectException(ValidationException::class);
        app(AssignHolidayChild::class)->handle($this->holiday(), $this->child(EventType::Walk, $start, $end));
    }

    /** @return array<string, array{string, string}> */
    public static function outsideHolidayDates(): array
    {
        return [
            'starts before holiday' => ['2026-10-02 15:59:00', '2026-10-03 12:00:00'],
            'ends after holiday' => ['2026-10-04 09:00:00', '2026-10-05 10:01:00'],
        ];
    }

    public function test_child_walk_and_social_keep_their_own_event_and_extension_identities(): void
    {
        $parent = $this->holiday();
        $walk = $this->child(EventType::Walk, '2026-10-03 09:00:00', '2026-10-03 15:00:00');
        $social = $this->child(EventType::Social, '2026-10-03 19:00:00', '2026-10-03 22:00:00');
        app(SaveSocialDetails::class)->handle($social, ['venue_name' => 'Seaview Lodge']);

        app(AssignHolidayChild::class)->handle($parent, $walk);
        app(AssignHolidayChild::class)->handle($parent, $social);

        $this->assertNotSame($parent->id, $walk->id);
        $this->assertNotSame($walk->id, $social->id);
        $this->assertSame($social->id, $social->social->event_id);
        $this->assertCount(2, $parent->children);
    }

    public function test_gallery_aggregation_seam_names_parent_and_child_event_sources_for_public_media(): void
    {
        $parent = $this->holiday();
        $child = $this->child(EventType::Social, '2026-10-03 19:00:00', '2026-10-03 22:00:00');
        app(AssignHolidayChild::class)->handle($parent, $child);

        $source = HolidayGallerySource::for($parent->fresh());

        $this->assertSame([$parent->id, $child->id], $source->eventIds);
        $this->assertTrue($source->mediaImplemented);
        $this->assertSame([$parent->id], HolidayGallerySource::forPublic($parent->fresh())->eventIds);
    }

    public function test_administrator_can_attach_a_child_from_the_holiday_admin_workflow(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);
        $parent = $this->holiday();
        $child = $this->child(EventType::Social, '2026-10-03 19:00:00', '2026-10-03 22:00:00');
        $this->actingAs($administrator);

        Livewire::test(EditHoliday::class, ['record' => $parent->holiday->id])
            ->callAction('attachChild', data: ['child_event_id' => $child->id])
            ->assertHasNoActionErrors();

        $this->assertSame($parent->id, $child->fresh()->parent_event_id);
    }

    public function test_child_can_be_detached_without_deleting_its_event_identity(): void
    {
        $parent = $this->holiday();
        $child = $this->child(EventType::Social, '2026-10-03 19:00:00', '2026-10-03 22:00:00');
        app(AssignHolidayChild::class)->handle($parent, $child);

        app(RemoveHolidayChild::class)->handle($parent, $child);

        $this->assertNull($child->fresh()->parent_event_id);
        $this->assertDatabaseHas('events', ['id' => $child->id]);
    }

    public function test_holiday_dates_cannot_be_changed_to_exclude_an_attached_child(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);
        $parent = $this->holiday();
        $child = $this->child(EventType::Social, '2026-10-03 19:00:00', '2026-10-03 22:00:00');
        app(AssignHolidayChild::class)->handle($parent, $child);

        try {
            app(UpdateHoliday::class)->handle($parent->holiday, $administrator, [
                'title' => $parent->title,
                'slug' => $parent->slug,
                'starts_at' => '2026-10-04 09:00:00',
                'ends_at' => '2026-10-05 10:00:00',
            ]);
            $this->fail('Expected child date validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('dates', $exception->errors());
        }

        $this->assertSame('2026-10-02 16:00:00', $parent->fresh()->starts_at->format('Y-m-d H:i:s'));
    }

    private function holiday(string $title = 'Coast weekend'): Event
    {
        $event = Event::factory()->create([
            'type' => EventType::Holiday, 'title' => $title, 'slug' => str($title)->slug(),
            'starts_at' => '2026-10-02 16:00:00', 'ends_at' => '2026-10-05 10:00:00',
        ]);
        app(SaveHolidayDetails::class)->handle($event, []);

        return $event->refresh();
    }

    private function child(EventType $type, string $start, string $end): Event
    {
        return Event::factory()->create(['type' => $type, 'starts_at' => $start, 'ends_at' => $end]);
    }
}
