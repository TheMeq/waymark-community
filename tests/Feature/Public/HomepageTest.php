<?php

namespace Tests\Feature\Public;

use App\Domain\Content\Models\HomepageSection;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\Models\SpecialAlbum;
use App\Models\User;
use App\ViewModels\HomepageViewModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class HomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_keeps_phase_two_content_but_uses_an_empty_real_media_source_until_photos_exist(): void
    {
        $homepage = HomepageViewModel::demo();

        $this->assertSame('Waymark Community', $homepage->site['name']);
        $this->assertCount(3, $homepage->upcomingWalks);
        $this->assertCount(6, $homepage->gallery);

        $this->get('/')
            ->assertOk()
            ->assertViewIs('home')
            ->assertViewHas('homepage', fn (HomepageViewModel $rendered): bool => $rendered->upcomingWalks === [] && $rendered->gallery === []);
    }

    public function test_homepage_reuses_one_canonical_site_profile_read_per_request(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = str_replace(['"', '`'], '', strtolower($query->sql));
        });

        $this->get('/')->assertSuccessful();

        $this->assertCount(1, collect($queries)->filter(
            fn (string $sql): bool => str_contains($sql, 'from site_profiles'),
        ));
    }

    public function test_homepage_preserves_the_approved_section_hierarchy(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder([
                'Great walks. Good people.',
                'Shared adventures.',
                'Upcoming walks',
                'Holidays & Weekends Away',
                'Photos from our walks & holidays',
                'Join us!',
                'Member resources',
            ]);
    }

    public function test_homepage_gallery_includes_the_member_upload_prompt_without_an_upload_workflow(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Members can upload photos linked to specific walks and holidays.')
            ->assertSee('Upload your photos')
            ->assertSee('href="'.route('community-photos.upload.create').'"', false);
    }

    public function test_publicly_eligible_pinned_gallery_photo_is_presented_first(): void
    {
        Storage::fake('local');
        $automatic = $this->galleryPhoto('Newest automatic memory', ['captured_at' => now()]);
        $pinned = $this->galleryPhoto('Pinned older memory', ['captured_at' => now()->subYear()]);
        HomepageSection::query()->create([
            'section_key' => 'gallery', 'enabled' => true, 'sort_order' => 10,
            'layout_variant' => 'default', 'content_mode' => 'pinned',
            'pinned_type' => 'community_photo', 'pinned_id' => $pinned->id,
            'empty_behavior' => 'hide',
        ]);

        $content = $this->get('/')->assertOk()->getContent();
        $this->assertIsString($content);
        $this->assertLessThan(
            strpos($content, route('gallery.photos.image', [$automatic, 'thumbnail'])),
            strpos($content, route('gallery.photos.image', [$pinned, 'thumbnail'])),
        );

        $pinned->update(['moderation_status' => 'removed', 'published_at' => null]);
        $this->get('/')->assertOk()->assertDontSee(route('gallery.photos.image', [$pinned, 'thumbnail']), false);
    }

    public function test_homepage_uses_featured_memories_then_recent_eligible_photos_without_demo_duplicates_and_caps_at_six(): void
    {
        Storage::fake('local');
        $featured = $this->galleryPhoto('Featured memory', ['is_featured' => true, 'captured_at' => now()->subMinutes(3), 'presentation_rotation' => 90, 'width' => 640, 'height' => 960]);
        $recent = $this->galleryPhoto('Recent memory', ['captured_at' => now()->subMinute()]);
        $duplicateFeatured = $this->galleryPhoto('Another featured memory', ['is_featured' => true, 'captured_at' => now()->subMinutes(2)]);
        $future = $this->galleryPhoto('Future memory', ['published_at' => now()->addMinute()]);
        foreach (range(1, 5) as $number) {
            $this->galleryPhoto('Older memory '.$number, ['captured_at' => now()->subMinutes(10 + $number)]);
        }

        $content = $this->get('/')->assertOk()->getContent();

        $this->assertIsString($content);
        $this->assertStringContainsString(route('gallery.photos.image', [$featured, 'thumbnail']), $content);
        $this->assertStringContainsString(route('gallery.photos.image', [$duplicateFeatured, 'thumbnail']), $content);
        $this->assertStringContainsString(route('gallery.photos.image', [$recent, 'thumbnail']), $content);
        $this->assertStringNotContainsString(route('gallery.photos.image', [$future, 'thumbnail']), $content);
        $this->assertSame(6, substr_count($content, 'data-homepage-memory'));
        $this->assertLessThan(strpos($content, route('gallery.photos.image', [$recent, 'thumbnail'])), strpos($content, route('gallery.photos.image', [$featured, 'thumbnail'])));
        $this->assertStringContainsString('width="960" height="640" style="transform: rotate(90deg)"', $content);
    }

    public function test_homepage_gallery_has_a_compact_empty_state_instead_of_demo_media(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSeeText('No recent photos yet.')
            ->assertDontSee('/images/demo/lakeside-friends.png', false);
    }

    public function test_homepage_keeps_the_phase_two_hero_image_but_does_not_render_demo_gallery_media(): void
    {
        $response = $this->get('/');

        $response->assertSee('src="/images/demo/hero-walkers-1536.webp"', false)
            ->assertSee('srcset="/images/demo/hero-walkers-768.webp 768w, /images/demo/hero-walkers-1536.webp 1536w"', false)
            ->assertSee('alt="Friends walking together across open moorland"', false)
            ->assertSee('style="object-position: 68% 32%"', false)
            ->assertDontSee('src="/images/demo/woodland-walk.png"', false)
            ->assertDontSee('src="/images/demo/lakeside-friends.png"', false);
    }

    public function test_demo_copy_does_not_hard_code_the_reference_installation(): void
    {
        $content = $this->get('/')->getContent();

        $this->assertIsString($content);
        $this->assertStringNotContainsString('NDWG', $content);
        $this->assertStringNotContainsString('Nottingham', $content);
        $this->assertStringNotContainsString('Derby', $content);
        $this->assertStringNotContainsString('20–50', $content);
    }

    /** @param array<string, mixed> $overrides */
    private function galleryPhoto(string $caption, array $overrides = []): CommunityPhoto
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
            'special_album_id' => SpecialAlbum::query()->create([
                'title' => 'Homepage memories '.$id,
                'slug' => 'homepage-memories-'.$id,
            ])->id,
            'uploader_id' => User::factory()->create()->id,
            'media_type' => 'image', 'processing_status' => 'complete', 'storage_disk' => 'local',
            'source_path' => $variants['master'], 'processed_variants' => $variants,
            'moderation_status' => 'approved', 'published_at' => now(), 'caption' => $caption,
        ], $overrides));
    }
}
