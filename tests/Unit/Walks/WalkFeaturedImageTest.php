<?php

namespace Tests\Unit\Walks;

use App\Domain\Events\Models\Event;
use App\Domain\SiteMedia\Enums\SiteMediaPurpose;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Domain\Walks\Data\WalkFeaturedImage;
use App\Domain\Walks\Models\Walk;
use App\Domain\Walks\Presentation\WalkFeaturedImagePresenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

final class WalkFeaturedImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_healthy_managed_media_wins_and_uses_owner_alt_and_requested_variant(): void
    {
        Storage::fake('local');
        URL::forceRootUrl('https://example.org/demo-site/ndwg');
        URL::forceScheme('https');
        $media = $this->media('Generic SiteMedia description');
        $walk = $this->walk('Managed ridge route', [
            'featured_image_media_id' => $media->id,
            'featured_image_path' => 'https://images.example.org/retained.jpg',
            'featured_image_alt_text' => 'Walkers crossing the ridge above the valley',
        ]);

        $image = app(WalkFeaturedImagePresenter::class)->present($walk->load('featuredMedia'), 'medium');

        $this->assertNotNull($image);
        $this->assertSame('https://example.org/demo-site/ndwg/media/'.$media->id.'/image/medium', $image->url);
        $this->assertSame('Walkers crossing the ridge above the valley', $image->alt);
        $this->assertSame([
            'url' => 'https://example.org/demo-site/ndwg/media/'.$media->id.'/image/medium',
            'alt' => 'Walkers crossing the ridge above the valley',
        ], $image->toArray());
    }

    public function test_unusable_managed_media_falls_back_to_safe_external_with_compatibility_alt(): void
    {
        Storage::fake('local');
        $media = $this->media('Generic SiteMedia description', storeVariantFiles: false);
        $walk = $this->walk('Fallback ridge route', [
            'featured_image_media_id' => $media->id,
            'featured_image_path' => 'https://images.example.org/retained.jpg',
        ]);
        Http::preventStrayRequests();

        $image = app(WalkFeaturedImagePresenter::class)->present($walk->load('featuredMedia'), 'large');

        $this->assertNotNull($image);
        $this->assertSame('https://images.example.org/retained.jpg', $image->url);
        $this->assertSame('Generic SiteMedia description', $image->alt);
    }

    public function test_demo_description_and_title_fallback_preserve_upgraded_external_compatibility(): void
    {
        URL::forceRootUrl('https://example.org/demo-site/ndwg');
        URL::forceScheme('https');
        Http::preventStrayRequests();

        $demo = app(WalkFeaturedImagePresenter::class)->present($this->walk('Demo route', [
            'featured_image_path' => '/images/demo/hero-walkers-768.webp',
        ]));
        $external = app(WalkFeaturedImagePresenter::class)->present($this->walk('Upgraded valley route', [
            'featured_image_path' => 'https://images.example.org/legacy.jpg',
        ]));

        $this->assertNotNull($demo);
        $this->assertSame('/demo-site/ndwg/images/demo/hero-walkers-768.webp', $demo->url);
        $this->assertSame('A group walking together across open moorland', $demo->alt);
        $this->assertNotNull($external);
        $this->assertSame('https://images.example.org/legacy.jpg', $external->url);
        $this->assertSame('Upgraded valley route featured image', $external->alt);
    }

    public function test_unsafe_or_absent_fallback_returns_no_presentation(): void
    {
        Storage::fake('local');
        $media = $this->media('Unavailable managed image', storeVariantFiles: false);

        $this->assertNull(app(WalkFeaturedImagePresenter::class)->present($this->walk('Unsafe route', [
            'featured_image_media_id' => $media->id,
            'featured_image_path' => '/storage/private/member-photo.jpg',
        ])->load('featuredMedia')));
        $this->assertNull(app(WalkFeaturedImagePresenter::class)->present($this->walk('Absent route')));
    }

    public function test_featured_image_is_a_presentation_only_value_object(): void
    {
        $image = new WalkFeaturedImage('/media/1/image/large', 'Walkers beside a lake');

        $this->assertSame([
            'url' => '/media/1/image/large',
            'alt' => 'Walkers beside a lake',
        ], $image->toArray());
    }

    /** @param array<string, mixed> $overrides */
    private function walk(string $title, array $overrides = []): Walk
    {
        $event = Event::factory()->create(['title' => $title]);
        $walk = Walk::query()->create(array_replace([
            'event_id' => $event->id,
            'primary_leader_id' => User::factory()->walkLeader()->create()->id,
        ], $overrides));
        $walk->setRelation('event', $event);

        return $walk;
    }

    private function media(string $alt, bool $storeVariantFiles = true): SiteMedia
    {
        $storageKey = (string) Str::uuid();
        $variants = [
            'master' => "site-media/{$storageKey}/master.jpg",
            'large' => "site-media/{$storageKey}/large.jpg",
            'medium' => "site-media/{$storageKey}/medium.jpg",
            'thumbnail' => "site-media/{$storageKey}/thumbnail.jpg",
        ];
        if ($storeVariantFiles) {
            foreach ($variants as $path) {
                Storage::disk('local')->put($path, 'safe image');
            }
        }

        return SiteMedia::query()->create([
            'created_by_user_id' => User::factory()->create()->id,
            'storage_disk' => 'local',
            'storage_key' => $storageKey,
            'processed_variants' => $variants,
            'mime_type' => 'image/jpeg',
            'width' => 1600,
            'height' => 1000,
            'file_size_bytes' => 1234,
            'alt_text' => $alt,
            'is_decorative' => false,
            'focal_point_x' => 0.5,
            'focal_point_y' => 0.5,
            'processing_status' => 'complete',
            'health_status' => 'healthy',
            'purpose' => SiteMediaPurpose::WalkFeaturedImage,
        ]);
    }
}
