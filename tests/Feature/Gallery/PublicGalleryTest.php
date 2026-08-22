<?php

namespace Tests\Feature\Gallery;

use App\Domain\Events\Models\Event;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\Models\SpecialAlbum;
use App\Domain\Gallery\Queries\PublicCommunityPhotos;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PublicGalleryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_gallery_lists_only_eligible_processed_published_photos(): void
    {
        $visible = $this->photo(['caption' => 'Visible summit']);
        $hidden = $this->photo(['caption' => 'Pending summit', 'moderation_status' => 'pending', 'published_at' => null]);

        $this->get('/photos')
            ->assertOk()
            ->assertSeeText('Visible summit')
            ->assertDontSeeText('Pending summit')
            ->assertSee(route('gallery.photos.image', [$visible, 'thumbnail']), false);

        $this->get(route('gallery.photos.image', [$hidden, 'thumbnail']))->assertNotFound();
    }

    public function test_future_scheduled_photo_is_not_public_until_its_publication_time(): void
    {
        $photo = $this->photo(['caption' => 'Future summit', 'published_at' => now()->addMinute()]);

        $this->get('/photos')->assertDontSeeText('Future summit');
        $this->get(route('gallery.photos.image', [$photo, 'thumbnail']))->assertNotFound();
        $this->get(route('community-photos.reports.create', $photo))->assertNotFound();
        $this->post(route('community-photos.reports.store', $photo), ['reason' => 'privacy', 'website' => ''])->assertRedirect()->assertSessionHas('status', 'Thank you. Your report has been received.');
        $this->assertDatabaseMissing('community_photo_reports', ['community_photo_id' => $photo->id]);

        $this->travel(61)->seconds();
        $this->get('/photos')->assertSeeText('Future summit');
        $this->get(route('gallery.photos.image', [$photo, 'thumbnail']))->assertOk();
    }

    public function test_manual_order_is_scoped_to_its_context_and_does_not_distort_recent_stream(): void
    {
        $oldPinned = $this->photo(['manual_sort_order' => 1, 'captured_at' => now()->subDays(5)]);
        $newerElsewhere = $this->photo(['captured_at' => now()->subDay()]);

        $this->assertSame([$newerElsewhere->id, $oldPinned->id], app(PublicCommunityPhotos::class)->recent()->pluck('id')->all());
        $this->assertSame([$oldPinned->id], app(PublicCommunityPhotos::class)->forEvent($oldPinned->event_id)->pluck('id')->all());
    }

    public function test_a_stable_public_image_url_is_revoked_immediately_after_removal(): void
    {
        $photo = $this->photo();
        $url = route('gallery.photos.image', [$photo, 'thumbnail']);

        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $photo->update(['moderation_status' => 'removed', 'published_at' => null]);
        $this->get($url)->assertNotFound();
    }

    public function test_gallery_uses_manual_order_then_capture_date_then_upload_order_without_duplicates(): void
    {
        $old = $this->photo(['captured_at' => now()->subDays(2), 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)]);
        $new = $this->photo(['captured_at' => now()->subDay(), 'created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);
        $override = $this->photo(['manual_sort_order' => 1, 'captured_at' => now()->subDays(10)]);
        $undated = $this->photo(['captured_at' => null, 'created_at' => now(), 'updated_at' => now()]);

        $ids = app(PublicCommunityPhotos::class)->recent()->pluck('id')->all();

        $this->assertSame([$undated->id, $new->id, $old->id, $override->id], $ids);
        $this->assertCount(4, array_unique($ids));
    }

    public function test_public_image_and_download_do_not_expose_original_or_private_records(): void
    {
        $photo = $this->photo();
        config()->set('gallery.public.downloads_enabled', true);

        $this->get(route('gallery.photos.image', [$photo, 'large']))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('gallery.photos.download', $photo))->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="waymark-photo-'.$photo->id.'.jpg"');
        $this->get(route('gallery.photos.image', [$photo, '../source']))->assertNotFound();
        config()->set('gallery.public.downloads_enabled', false);
        $this->get(route('gallery.photos.download', $photo))->assertNotFound();
    }

    public function test_gallery_is_context_first_with_eligible_event_and_album_counts(): void
    {
        $event = Event::factory()->create(['title' => 'Moorland morning', 'slug' => 'moorland-morning']);
        $album = SpecialAlbum::query()->create(['title' => 'Spring gathering', 'slug' => 'spring-gathering']);
        $this->photo(['event_id' => $event->id]);
        $this->photo(['event_id' => $event->id]);
        $this->photo(['event_id' => null, 'special_album_id' => $album->id]);

        $this->get('/photos')->assertOk()->assertSeeText('Moorland morning')->assertSeeText('2 photos')->assertSeeText('Spring gathering')->assertSeeText('1 photo');
        $this->get(route('gallery.events.show', $event))->assertOk()->assertSeeText('Moorland morning');
        $this->get(route('gallery.albums.show', $album))->assertOk()->assertSeeText('Spring gathering');
    }

    /** @param array<string, mixed> $overrides */
    private function photo(array $overrides = []): CommunityPhoto
    {
        $id = CommunityPhoto::query()->count() + 1;
        $directory = 'community-photos/3f2504e0-4f89-41d3-9a0c-'.str_pad((string) $id, 12, '0', STR_PAD_LEFT);
        $variants = [];
        foreach (['thumbnail', 'medium', 'large', 'master'] as $variant) {
            $path = $directory.'/'.$variant.'.jpg';
            Storage::disk('local')->put($path, $variant.' image');
            $variants[$variant] = $path;
        }

        return CommunityPhoto::query()->create(array_replace([
            'event_id' => Event::factory()->create()->id,
            'uploader_id' => User::factory()->create()->id,
            'media_type' => 'image', 'processing_status' => 'complete', 'storage_disk' => 'local',
            'source_path' => $variants['master'], 'processed_variants' => $variants,
            'moderation_status' => 'approved', 'published_at' => now(), 'caption' => 'A photo',
        ], $overrides));
    }
}
