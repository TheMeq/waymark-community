<?php

namespace Tests\Feature\Gallery;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Gallery\Actions\ModerateCommunityPhoto;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\Models\SpecialAlbum;
use App\Domain\Gallery\PublicCommunityPhotoPresenter;
use App\Domain\Gallery\Queries\PublicCommunityPhotos;
use App\Domain\Socials\Models\Social;
use App\Filament\Pages\PhotoModeration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class PublicPhotoEventContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config()->set('gallery.public.downloads_enabled', true);
    }

    public function test_public_event_photo_is_exposed_only_while_its_source_event_is_currently_public(): void
    {
        $event = $this->publicEvent('Public ridge walk');
        $photo = $this->photoFor($event);

        $this->assertNotNull(app(PublicCommunityPhotoPresenter::class)->present($photo));
        $this->assertSame([$photo->id], app(PublicCommunityPhotos::class)->recent(perPage: 10)->items->pluck('id')->all());
        $this->get(route('gallery.photos.show', $photo))->assertOk();
        $this->get(route('gallery.photos.image', [$photo, 'thumbnail']))->assertOk();
        $this->get(route('gallery.photos.download', $photo))->assertOk();

        $event->update(['is_public' => false]);
        $photo->unsetRelation('event');

        $this->assertNull(app(PublicCommunityPhotoPresenter::class)->present($photo));
        $this->assertSame([], app(PublicCommunityPhotos::class)->recent(perPage: 10)->items->pluck('id')->all());
        $this->assertNotContains('Public ridge walk', app(PublicCommunityPhotos::class)->contexts()->pluck('label')->all());
        $this->get(route('gallery.photos.show', $photo))->assertNotFound();
        $this->get(route('gallery.photos.image', [$photo, 'thumbnail']))->assertNotFound();
        $this->get(route('gallery.photos.download', $photo))->assertNotFound();
        $this->get(route('community-photos.reports.create', $photo))->assertNotFound();

        $event->update(['is_public' => true]);
        $photo->unsetRelation('event');

        $this->assertNotNull(app(PublicCommunityPhotoPresenter::class)->present($photo));
        $this->assertSame([$photo->id], app(PublicCommunityPhotos::class)->recent(perPage: 10)->items->pluck('id')->all());
    }

    public function test_draft_or_unpublished_event_hides_photo_without_changing_photo_state(): void
    {
        $event = $this->publicEvent('Status boundary');
        $photo = $this->photoFor($event);
        $photoState = $photo->fresh()->getAttributes();

        $event->update(['status' => EventStatus::Draft]);
        $this->assertNull(app(PublicCommunityPhotoPresenter::class)->present($photo));
        $this->assertSame([], app(PublicCommunityPhotos::class)->forEvent($event->id, perPage: 10)->items->all());

        $event->update(['status' => EventStatus::Published, 'published_at' => null]);
        $this->assertNull(app(PublicCommunityPhotoPresenter::class)->present($photo));
        $this->assertSame($photoState, $photo->fresh()->getAttributes());
    }

    public function test_special_album_photo_remains_public_without_an_event_context(): void
    {
        $album = SpecialAlbum::query()->create(['title' => 'Community highlights', 'slug' => 'community-highlights']);
        $photo = $this->photoFor($album);

        $this->assertNotNull(app(PublicCommunityPhotoPresenter::class)->present($photo));
        $this->assertSame([$photo->id], app(PublicCommunityPhotos::class)->forAlbum($album->id, perPage: 10)->items->pluck('id')->all());
        $this->get(route('gallery.photos.show', $photo))->assertOk();
        $this->get(route('gallery.albums.show', $album))->assertOk()->assertSeeText('Community highlights');
    }

    public function test_global_and_organiser_moderation_cannot_target_ineligible_events(): void
    {
        $moderator = User::factory()->create(['role' => AccountRole::Moderator]);
        $leader = User::factory()->create(['role' => AccountRole::WalkLeader]);
        $globalSource = $this->photoFor($this->publicEvent('Global source'), ['moderation_status' => 'pending', 'published_at' => null]);
        $leaderSource = $this->photoFor($this->publicEvent('Leader source', $leader), ['moderation_status' => 'pending', 'published_at' => null]);
        $eligible = $this->publicEvent('Eligible destination');
        $private = $this->publicEvent('Private destination');
        $private->update(['is_public' => false]);
        $leaderPrivate = $this->publicEvent('Leader private destination', $leader);
        $leaderPrivate->update(['status' => EventStatus::Draft]);

        $globalOptions = Livewire::actingAs($moderator)->test(PhotoModeration::class)->instance()->contextOptions();
        $leaderOptions = Livewire::actingAs($leader)->test(PhotoModeration::class)->instance()->contextOptions();
        $this->assertArrayHasKey('event:'.$eligible->id, $globalOptions);
        $this->assertArrayNotHasKey('event:'.$private->id, $globalOptions);
        $this->assertArrayNotHasKey('event:'.$leaderPrivate->id, $leaderOptions);

        foreach ([
            [$moderator, $globalSource, $private],
            [$leader, $leaderSource, $leaderPrivate],
        ] as [$actor, $photo, $target]) {
            try {
                app(ModerateCommunityPhoto::class)->move($actor, $photo, 'event:'.$target->id);
                $this->fail('A photo was moved into an ineligible Event context.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('context', $exception->errors());
            }
        }

        $this->assertNotSame($private->id, $globalSource->fresh()->event_id);
        $this->assertNotSame($leaderPrivate->id, $leaderSource->fresh()->event_id);
        $this->assertDatabaseCount('community_photo_moderation_audits', 0);

        app(ModerateCommunityPhoto::class)->move($moderator, $globalSource, 'event:'.$eligible->id);
        $this->assertSame($eligible->id, $globalSource->fresh()->event_id);
    }

    private function publicEvent(string $title, ?User $organiser = null): Event
    {
        $event = Event::factory()->for($organiser ?? User::factory()->create(), 'organiser')->create([
            'type' => EventType::Social,
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'status' => EventStatus::Published,
            'is_public' => true,
            'published_at' => now()->subMinute(),
        ]);
        Social::query()->create(['event_id' => $event->id, 'venue_name' => 'Village hall']);

        return $event;
    }

    /** @param array<string, mixed> $overrides */
    private function photoFor(Event|SpecialAlbum $context, array $overrides = []): CommunityPhoto
    {
        $sequence = CommunityPhoto::query()->count() + 1;
        $path = 'community-photos/3f2504e0-4f89-41d3-9a0c-'.str_pad((string) $sequence, 12, '0', STR_PAD_LEFT).'/master.jpg';
        Storage::disk('local')->put($path, 'safe image bytes');

        return CommunityPhoto::query()->create(array_replace([
            'event_id' => $context instanceof Event ? $context->id : null,
            'special_album_id' => $context instanceof SpecialAlbum ? $context->id : null,
            'uploader_id' => User::factory()->create()->id,
            'media_type' => 'image',
            'processing_status' => 'complete',
            'storage_disk' => 'local',
            'source_path' => $path,
            'processed_variants' => ['master' => $path],
            'caption' => 'Public context photo',
            'moderation_status' => 'approved',
            'published_at' => now()->subMinute(),
        ], $overrides));
    }
}
