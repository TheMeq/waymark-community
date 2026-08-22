<?php

namespace Tests\Feature\SiteMedia;

use App\Domain\Events\Models\Event;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\SiteMedia\Actions\MarkSiteMediaForRepair;
use App\Domain\SiteMedia\Actions\PromoteCommunityPhotoToSiteMedia;
use App\Domain\SiteMedia\Actions\RegenerateSiteMedia;
use App\Domain\SiteMedia\Actions\SiteMediaNamespaceCleaner;
use App\Domain\SiteMedia\Actions\UpdateSiteMediaMetadata;
use App\Domain\SiteMedia\Actions\UploadSiteMedia;
use App\Domain\SiteMedia\Data\SiteMediaMetadata;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

final class SiteMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_media_requires_meaningful_alt_text_unless_decorative(): void
    {
        $this->expectException(\LogicException::class);

        SiteMedia::query()->create($this->attributes(['alt_text' => null, 'is_decorative' => false]));
    }

    public function test_promoting_an_approved_safe_photo_copies_its_derivative_into_the_site_media_namespace(): void
    {
        Storage::fake('local');
        $actor = User::factory()->create(['is_admin' => true]);
        $photo = $this->approvedPhoto($actor);
        Storage::disk('local')->put($photo->processed_variants['master'], $this->safeRaster());

        $media = app(PromoteCommunityPhotoToSiteMedia::class)->handle($actor, $photo, new SiteMediaMetadata('Hill walkers', false, 0.3, 0.7));

        $this->assertSame($photo->id, $media->source_community_photo_id);
        $this->assertStringStartsWith('site-media/', $media->processed_variants['master']);
        $this->assertTrue(Storage::disk('local')->exists($media->processed_variants['master']));
        $this->assertTrue(Storage::disk('local')->exists($photo->processed_variants['master']));
    }

    public function test_metadata_update_enforces_focal_bounds_and_records_an_audit(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $media = SiteMedia::query()->create($this->attributes());

        app(UpdateSiteMediaMetadata::class)->handle($actor, $media, new SiteMediaMetadata('A bright ridge', false, 0.25, 0.75));

        $this->assertSame('A bright ridge', $media->fresh()->alt_text);
        $this->assertSame('metadata_updated', $media->audits()->sole()->action);
        $this->expectException(ValidationException::class);
        app(UpdateSiteMediaMetadata::class)->handle($actor, $media, new SiteMediaMetadata('A bright ridge', false, 1.1, 0.5));
    }

    public function test_promoted_copy_survives_source_photo_deletion(): void
    {
        Storage::fake('local');
        $actor = User::factory()->create(['is_admin' => true]);
        $photo = $this->approvedPhoto($actor);
        Storage::disk('local')->put($photo->processed_variants['master'], $this->safeRaster());
        $media = app(PromoteCommunityPhotoToSiteMedia::class)->handle($actor, $photo, new SiteMediaMetadata('Hill walkers', false));

        $photo->delete();

        $this->assertNull($media->fresh()->source_community_photo_id);
        $this->assertTrue(Storage::disk('local')->exists($media->processed_variants['master']));
    }

    public function test_public_stream_never_exposes_storage_paths_and_returns_no_store_response(): void
    {
        Storage::fake('local');
        $media = SiteMedia::query()->create($this->attributes());
        Storage::disk('local')->put($media->processed_variants['master'], 'safe image');

        $this->get(route('site-media.stream', [$media, 'master']))
            ->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff')->assertDontSee('site-media/', false);
    }

    public function test_public_stream_returns_not_found_for_unsafe_or_missing_variants(): void
    {
        Storage::fake('local');
        $media = SiteMedia::query()->create($this->attributes(['processed_variants' => ['master' => 'site-media/3f2504e0-4f89-41d3-9a0c-0305e82c3300/master.jpg']]));

        $this->get(route('site-media.stream', [$media, 'master']))->assertNotFound();
    }

    public function test_safe_upload_creates_only_site_media_derivatives(): void
    {
        Storage::fake('local');
        $actor = User::factory()->create(['is_admin' => true]);

        $media = app(UploadSiteMedia::class)->handle($actor, UploadedFile::fake()->image('ridge.jpg', 1200, 800), new SiteMediaMetadata('Walkers on a ridge', false));

        $this->assertStringStartsWith('site-media/', $media->processed_variants['master']);
        $this->assertTrue(Storage::disk('local')->exists($media->processed_variants['master']));
        $this->assertSame(0, CommunityPhoto::query()->count());
        $this->assertSame('uploaded', $media->audits()->sole()->action);
    }

    public function test_direct_model_save_rejects_out_of_bounds_focal_point(): void
    {
        $this->expectException(\LogicException::class);
        SiteMedia::query()->create($this->attributes(['focal_point_x' => 1.01]));
    }

    public function test_repair_action_marks_missing_derivatives_and_is_idempotently_audited(): void
    {
        Storage::fake('local');
        $actor = User::factory()->create(['is_admin' => true]);
        $media = SiteMedia::query()->create($this->attributes());

        app(MarkSiteMediaForRepair::class)->handle($actor, $media);
        app(MarkSiteMediaForRepair::class)->handle($actor, $media);

        $this->assertSame('repair_required', $media->fresh()->health_status);
        $this->assertSame(['repair_required'], $media->audits()->pluck('action')->all());
    }

    public function test_unprivileged_user_cannot_promote_or_edit_site_media(): void
    {
        $this->expectException(AuthorizationException::class);
        app(UpdateSiteMediaMetadata::class)->handle(User::factory()->create(), SiteMedia::query()->create($this->attributes()), new SiteMediaMetadata('No access', false));
    }

    public function test_regeneration_retains_old_namespace_for_durable_retry_when_cleanup_fails(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $photo = $this->approvedPhoto($actor);
        $media = SiteMedia::query()->create($this->attributes(['source_community_photo_id' => $photo->id]));
        $replacement = SiteMedia::query()->create($this->attributes([
            'storage_key' => '3f2504e0-4f89-41d3-9a0c-0305e82c3311',
            'processed_variants' => ['master' => 'site-media/3f2504e0-4f89-41d3-9a0c-0305e82c3311/master.jpg'],
        ]));
        $promotion = Mockery::mock(PromoteCommunityPhotoToSiteMedia::class);
        $promotion->shouldReceive('handle')->once()->andReturn($replacement);
        $cleaner = Mockery::mock(SiteMediaNamespaceCleaner::class);
        $cleaner->shouldReceive('delete')->once()->with('local', $media->storage_key)->andReturnFalse();

        app(RegenerateSiteMedia::class, ['promotion' => $promotion, 'cleaner' => $cleaner])->handle($actor, $media);

        $media->refresh();
        $this->assertSame($replacement->storage_key, $media->storage_key);
        $this->assertSame('failed', $media->regeneration_cleanup_status);
        $this->assertSame('3f2504e0-4f89-41d3-9a0c-0305e82c3300', $media->regeneration_cleanup_storage_key);
        $this->assertFalse(SiteMedia::query()->whereKey($replacement->id)->exists());
        $this->assertSame(['regenerated', 'regeneration_cleanup_failed'], $media->audits()->pluck('action')->all());
    }

    public function test_regeneration_cleanup_retry_only_removes_the_retained_old_namespace(): void
    {
        Storage::fake('local');
        $actor = User::factory()->create(['is_admin' => true]);
        $media = SiteMedia::query()->create($this->attributes([
            'storage_key' => '3f2504e0-4f89-41d3-9a0c-0305e82c3311',
            'processed_variants' => ['master' => 'site-media/3f2504e0-4f89-41d3-9a0c-0305e82c3311/master.jpg'],
            'regeneration_cleanup_status' => 'failed',
            'regeneration_cleanup_storage_disk' => 'local',
            'regeneration_cleanup_storage_key' => '3f2504e0-4f89-41d3-9a0c-0305e82c3300',
        ]));
        $oldPath = 'site-media/3f2504e0-4f89-41d3-9a0c-0305e82c3300/master.jpg';
        $newPath = $media->processed_variants['master'];
        Storage::disk('local')->put($oldPath, 'old');
        Storage::disk('local')->put($newPath, 'new');

        $this->assertTrue(app(RegenerateSiteMedia::class)->retryCleanup($actor, $media));

        $media->refresh();
        $this->assertNull($media->regeneration_cleanup_status);
        $this->assertNull($media->regeneration_cleanup_storage_key);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($newPath);
        $this->assertSame('regeneration_cleanup_completed', $media->audits()->latest('id')->value('action'));
    }

    /** @return array<string, mixed> */
    private function attributes(array $overrides = []): array
    {
        return array_replace([
            'created_by_user_id' => User::factory()->create()->id,
            'storage_disk' => 'local',
            'storage_key' => '3f2504e0-4f89-41d3-9a0c-0305e82c3300',
            'processed_variants' => ['master' => 'site-media/3f2504e0-4f89-41d3-9a0c-0305e82c3300/master.jpg'],
            'mime_type' => 'image/jpeg', 'width' => 1600, 'height' => 1000, 'file_size_bytes' => 1234,
            'alt_text' => 'Walkers on a ridge', 'is_decorative' => false,
            'focal_point_x' => 0.5, 'focal_point_y' => 0.5, 'processing_status' => 'complete', 'health_status' => 'healthy',
        ], $overrides);
    }

    private function approvedPhoto(User $actor): CommunityPhoto
    {
        $event = Event::factory()->create();

        return CommunityPhoto::query()->create([
            'event_id' => $event->id, 'uploader_id' => $actor->id, 'media_type' => 'image', 'processing_status' => 'complete',
            'storage_disk' => 'local', 'source_path' => 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3300/master.jpg',
            'processed_variants' => ['master' => 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c3300/master.jpg'],
            'moderation_status' => 'approved', 'published_at' => now()->subMinute(),
        ]);
    }

    private function safeRaster(): string
    {
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgYGAAAAAEAAGjChXjAAAAAElFTkSuQmCC', true);
    }
}
