<?php

namespace Tests\Feature\Gallery;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Gallery\Actions\ModerateCommunityPhoto;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\Models\CommunityPhotoModerationAudit;
use App\Domain\Gallery\Queries\HolidayCommunityPhotos;
use App\Domain\Holidays\Actions\AssignHolidayChild;
use App\Domain\Holidays\Actions\RemoveHolidayChild;
use App\Domain\Holidays\Actions\SaveHolidayDetails;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class HolidayGalleryAndFeaturedMemoriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_holiday_gallery_aggregates_current_children_without_reparenting_and_detach_removes_their_photos(): void
    {
        $holiday = $this->holiday();
        $child = $this->child($holiday, EventType::Walk, ['title' => 'Coast path walk', 'slug' => 'coast-path-walk']);
        app(AssignHolidayChild::class)->handle($holiday, $child);
        $photo = $this->photo($child, ['caption' => 'At the headland']);

        $page = app(HolidayCommunityPhotos::class)->forHoliday($holiday);

        $this->assertSame([$photo->id], $page->items->pluck('id')->all());
        $this->assertSame('Coast path walk', $page->items->sole()->contextLabel);
        $this->assertSame(route('gallery.events.show', $child), $page->items->sole()->contextUrl);
        $this->assertSame($child->id, $photo->fresh()->event_id);

        app(RemoveHolidayChild::class)->handle($holiday, $child);

        $this->assertTrue(app(HolidayCommunityPhotos::class)->forHoliday($holiday)->isEmpty());
        $this->assertSame($child->id, $photo->fresh()->event_id);
    }

    public function test_holiday_gallery_uses_the_strict_public_presenter_and_bounded_duplicate_free_continuation(): void
    {
        config()->set('gallery.public.per_page', 1);
        $holiday = $this->holiday();
        $child = $this->child($holiday, EventType::Social);
        app(AssignHolidayChild::class)->handle($holiday, $child);
        $visible = $this->photo($child, ['captured_at' => now()->subMinute()]);
        $missing = $this->photo($holiday, ['captured_at' => now()->subMinutes(2)]);
        $later = $this->photo($holiday, ['captured_at' => now()->subMinutes(3)]);
        $future = $this->photo($child, ['caption' => 'Future', 'published_at' => now()->addMinute()]);
        Storage::disk('local')->delete(array_values($missing->processed_variants));

        $first = app(HolidayCommunityPhotos::class)->forHoliday($holiday);
        $second = app(HolidayCommunityPhotos::class)->forHoliday($holiday, $first->nextCursor);

        $this->assertSame([$visible->id], $first->items->pluck('id')->all());
        $this->assertSame([$later->id], $second->items->pluck('id')->all());
        $this->assertSame([], array_values(array_intersect($first->items->pluck('id')->all(), $second->items->pluck('id')->all())));
        $this->assertNotContains($future->id, [...$first->items->pluck('id'), ...$second->items->pluck('id')]);
    }

    public function test_completed_public_child_memories_remain_in_holiday_and_direct_event_galleries(): void
    {
        $holiday = $this->holiday();
        $child = $this->child($holiday, EventType::Walk, ['status' => EventStatus::Completed, 'is_public' => true, 'published_at' => now()]);
        app(AssignHolidayChild::class)->handle($holiday, $child);
        $photo = $this->photo($child);

        $this->assertSame([$photo->id], app(HolidayCommunityPhotos::class)->forHoliday($holiday)->items->pluck('id')->all());
        $this->get(route('gallery.events.show', $child->slug))->assertOk()->assertSee($photo->caption);
    }

    public function test_featured_memory_can_be_unfeatured_idempotently_with_audit_but_never_when_not_current_public(): void
    {
        $moderator = User::factory()->create(['role' => AccountRole::Moderator]);
        $photo = $this->photo(Event::factory()->create(), ['is_featured' => true]);
        $action = app(ModerateCommunityPhoto::class);

        $action->unfeature($moderator, $photo);
        $action->unfeature($moderator, $photo);

        $this->assertFalse($photo->fresh()->is_featured);
        $this->assertSame(['unfeatured'], CommunityPhotoModerationAudit::query()->pluck('action')->all());

        $stale = $this->photo(Event::factory()->create(), ['is_featured' => true, 'published_at' => now()->addMinute()]);
        Storage::disk('local')->delete(array_values($stale->processed_variants));
        $action->unfeature($moderator, $stale);
        $this->assertFalse($stale->fresh()->is_featured);
        $this->assertSame(['unfeatured', 'unfeatured'], CommunityPhotoModerationAudit::query()->pluck('action')->all());

        $scheduled = $this->photo(Event::factory()->create(), ['published_at' => now()->addMinute()]);
        $this->expectException(ValidationException::class);
        $action->feature($moderator, $scheduled);
    }

    private function holiday(): Event
    {
        $holiday = Event::factory()->create([
            'type' => EventType::Holiday,
            'starts_at' => now()->addWeek()->startOfDay(),
            'ends_at' => now()->addWeek()->addDays(3)->endOfDay(),
            'status' => EventStatus::Published,
            'is_public' => true,
            'published_at' => now(),
        ]);
        app(SaveHolidayDetails::class)->handle($holiday, []);

        return $holiday->refresh();
    }

    /** @param array<string, mixed> $overrides */
    private function child(Event $holiday, EventType $type, array $overrides = []): Event
    {
        $startsAt = $holiday->starts_at->copy()->addDay()->setTime(10, 0);

        return Event::factory()->create([
            'type' => $type,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
            'status' => EventStatus::Published,
            'is_public' => true,
            'published_at' => now(),
            ...$overrides,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function photo(Event $event, array $overrides = []): CommunityPhoto
    {
        $id = CommunityPhoto::query()->count() + 1;
        $directory = 'community-photos/3f2504e0-4f89-41d3-9a0c-'.str_pad((string) $id, 12, '0', STR_PAD_LEFT);
        $variants = [];
        foreach (['thumbnail', 'medium', 'large', 'master'] as $variant) {
            $path = $directory.'/'.$variant.'.jpg';
            Storage::disk('local')->put($path, $variant);
            $variants[$variant] = $path;
        }

        return CommunityPhoto::query()->create(array_replace([
            'event_id' => $event->id,
            'uploader_id' => User::factory()->create()->id,
            'media_type' => 'image', 'processing_status' => 'complete', 'storage_disk' => 'local',
            'source_path' => $variants['master'], 'processed_variants' => $variants,
            'moderation_status' => 'approved', 'published_at' => now(), 'caption' => 'Community memory',
        ], $overrides));
    }
}
