<?php

namespace Tests\Feature\SiteMedia;

use App\Domain\Content\Models\CmsPage;
use App\Domain\Content\Models\NewsArticle;
use App\Domain\Content\Models\Testimonial;
use App\Domain\Events\Models\Event;
use App\Domain\Governance\Models\CommitteeRole;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\SiteMedia\Actions\DeleteSiteMedia;
use App\Domain\SiteMedia\Actions\DiscardUnattachedSiteMedia;
use App\Domain\SiteMedia\Actions\MarkSiteMediaOrphaned;
use App\Domain\SiteMedia\Actions\SiteMediaNamespaceCleaner;
use App\Domain\SiteMedia\Enums\SiteMediaPurpose;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Domain\SiteMedia\Queries\SiteMediaUsage;
use App\Domain\Walks\Models\Walk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

final class SiteMediaUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_owner_reference_is_reported_safely_and_blocks_deletion_and_orphaning(): void
    {
        Storage::fake('local');
        $actor = User::factory()->create(['is_admin' => true]);
        $author = User::factory()->create();

        $cases = [
            'CMS page hero' => function (SiteMedia $media): void {
                CmsPage::query()->create([
                    'title' => 'Private page title',
                    'slug' => 'private-page-title',
                    'hero_media_id' => $media->id,
                    'blocks' => [],
                    'publication_state' => 'draft',
                ]);
            },
            'News featured image' => function (SiteMedia $media) use ($author): void {
                NewsArticle::query()->create([
                    'title' => 'Private news title',
                    'slug' => 'private-news-title',
                    'blocks' => [],
                    'featured_media_id' => $media->id,
                    'author_id' => $author->id,
                    'primary_category' => 'Private category',
                    'publication_state' => 'draft',
                ]);
            },
            'Testimonial image' => function (SiteMedia $media): void {
                Testimonial::query()->create([
                    'quote' => 'Private testimonial text',
                    'display_name' => 'Private member name',
                    'image_media_id' => $media->id,
                ]);
            },
            'Committee role public photo' => function (SiteMedia $media): void {
                CommitteeRole::query()->create([
                    'title' => 'Private committee title',
                    'public_name' => 'Private member name',
                    'public_photo_media_id' => $media->id,
                    'private_email' => 'private@example.test',
                ]);
            },
            'Walk featured image' => function (SiteMedia $media) use ($author): void {
                $event = Event::factory()->for($author, 'organiser')->create();
                Walk::query()->create([
                    'event_id' => $event->id,
                    'primary_leader_id' => $author->id,
                    'featured_image_media_id' => $media->id,
                    'featured_image_alt_text' => 'Walkers following a ridge path',
                ]);
            },
            'Site logo' => function (SiteMedia $media): void {
                SiteProfile::query()->updateOrCreate(
                    ['id' => SiteProfile::SINGLETON_ID],
                    ['group_name' => 'Private group name', 'logo_media_id' => $media->id],
                );
            },
            'Site favicon' => function (SiteMedia $media): void {
                SiteProfile::query()->updateOrCreate(
                    ['id' => SiteProfile::SINGLETON_ID],
                    ['group_name' => 'Private group name', 'favicon_media_id' => $media->id],
                );
            },
        ];

        foreach ($cases as $label => $attach) {
            $purpose = match ($label) {
                'Site logo' => SiteMediaPurpose::SiteLogo,
                'Site favicon' => SiteMediaPurpose::SiteFavicon,
                default => SiteMediaPurpose::WalkFeaturedImage,
            };
            $media = $this->media($actor, ['purpose' => $purpose]);
            $path = $media->processed_variants['master'];
            Storage::disk('local')->put($path, 'safe image');
            $attach($media);

            $usage = app(SiteMediaUsage::class);
            $this->assertTrue($usage->isUsed($media));
            $this->assertSame([$label], $usage->labelsFor($media));

            try {
                app(DeleteSiteMedia::class)->handle($actor, $media);
                $this->fail("Referenced media was deleted for {$label}.");
            } catch (ValidationException $exception) {
                $this->assertSame(
                    ["This media item is currently used as: {$label}. Remove that reference before deleting it."],
                    $exception->errors()['media'],
                );
            }

            $media->refresh();
            $this->assertSame('complete', $media->processing_status);
            $this->assertSame('healthy', $media->health_status);
            $this->assertNotEmpty($media->processed_variants);
            $this->assertNull($media->orphaned_at);
            Storage::disk('local')->assertExists($path);
            $this->assertDatabaseCount('site_media_audits', 0);

            $this->assertFalse(app(MarkSiteMediaOrphaned::class)->handle($media));
            $this->assertNull($media->fresh()->orphaned_at);
        }
    }

    public function test_unreferenced_purpose_bound_media_is_marked_orphaned_idempotently_but_library_media_is_not(): void
    {
        $actor = User::factory()->create();
        $purposeBound = $this->media($actor, ['purpose' => SiteMediaPurpose::WalkFeaturedImage]);
        $library = $this->media($actor, ['purpose' => SiteMediaPurpose::Library]);

        $this->assertTrue(app(MarkSiteMediaOrphaned::class)->handle($purposeBound));
        $firstMarkedAt = $purposeBound->fresh()->orphaned_at;
        $this->assertNotNull($firstMarkedAt);

        $this->assertTrue(app(MarkSiteMediaOrphaned::class)->handle($purposeBound));
        $this->assertTrue($purposeBound->fresh()->orphaned_at->equalTo($firstMarkedAt));

        $this->assertFalse(app(MarkSiteMediaOrphaned::class)->handle($library));
        $this->assertNull($library->fresh()->orphaned_at);
    }

    public function test_discard_rechecks_current_usage_and_never_discards_library_or_attached_media(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $cleaner = Mockery::mock(SiteMediaNamespaceCleaner::class);
        $cleaner->shouldNotReceive('delete');
        $this->app->instance(SiteMediaNamespaceCleaner::class, $cleaner);

        $library = $this->media($actor, [
            'purpose' => SiteMediaPurpose::Library,
            'orphaned_at' => now()->subMinute(),
        ]);
        $unmarked = $this->media($actor, ['purpose' => SiteMediaPurpose::WalkFeaturedImage]);
        $newlyAttached = $this->media($actor, [
            'purpose' => SiteMediaPurpose::WalkFeaturedImage,
            'orphaned_at' => now()->subMinute(),
        ]);
        $staleCandidate = $newlyAttached->fresh();
        CmsPage::query()->create([
            'title' => 'Attached after candidate discovery',
            'slug' => 'attached-after-candidate-discovery',
            'hero_media_id' => $newlyAttached->id,
            'blocks' => [],
            'publication_state' => 'draft',
        ]);

        $discard = app(DiscardUnattachedSiteMedia::class);
        $this->assertFalse($discard->handle($actor, $library));
        $this->assertFalse($discard->handle($actor, $unmarked));
        $this->assertFalse($discard->handle($actor, $staleCandidate));

        foreach ([$library, $unmarked, $newlyAttached] as $media) {
            $this->assertSame('healthy', $media->fresh()->health_status);
            $this->assertNotEmpty($media->fresh()->processed_variants);
        }
    }

    public function test_orphan_cleanup_failure_is_recorded_and_can_be_retried(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $media = $this->media($actor, [
            'purpose' => SiteMediaPurpose::WalkFeaturedImage,
            'orphaned_at' => now()->subMinute(),
        ]);
        $cleaner = Mockery::mock(SiteMediaNamespaceCleaner::class);
        $cleaner->shouldReceive('delete')->twice()->with('local', $media->storage_key)->andReturn(false, true);
        $this->app->instance(SiteMediaNamespaceCleaner::class, $cleaner);

        $discard = app(DiscardUnattachedSiteMedia::class);
        $this->assertFalse($discard->handle($actor, $media));
        $this->assertSame('deletion_failed', $media->fresh()->health_status);

        $this->assertTrue($discard->handle($actor, $media->fresh()));
        $this->assertSame('removed', $media->fresh()->health_status);
        $this->assertSame(
            ['removal_requested', 'deletion_failed', 'removed'],
            $media->audits()->pluck('action')->all(),
        );
    }

    /** @param array<string, mixed> $overrides */
    private function media(User $actor, array $overrides = []): SiteMedia
    {
        $storageKey = (string) Str::uuid();
        $purpose = $overrides['purpose'] ?? SiteMediaPurpose::Library;
        $decorative = in_array($purpose, [SiteMediaPurpose::SiteLogo, SiteMediaPurpose::SiteFavicon], true);

        return SiteMedia::query()->create(array_replace([
            'created_by_user_id' => $actor->id,
            'storage_disk' => 'local',
            'storage_key' => $storageKey,
            'processed_variants' => ['master' => "site-media/{$storageKey}/master.jpg"],
            'mime_type' => 'image/jpeg',
            'width' => 1600,
            'height' => 1000,
            'file_size_bytes' => 1234,
            'alt_text' => $decorative ? null : 'Walkers on a ridge',
            'is_decorative' => $decorative,
            'focal_point_x' => 0.5,
            'focal_point_y' => 0.5,
            'processing_status' => 'complete',
            'health_status' => 'healthy',
            'purpose' => $purpose,
        ], $overrides));
    }
}
