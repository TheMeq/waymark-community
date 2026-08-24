<?php

namespace Tests\Feature\Quality;

use App\Domain\Content\Models\FooterSection;
use App\Domain\Content\Models\HomepageSection;
use App\Domain\Content\Models\NavigationItem;
use App\Domain\Content\Models\Testimonial;
use App\Domain\Content\Queries\PublicBranding;
use App\Domain\Content\Queries\PublicFooterSections;
use App\Domain\Content\Queries\PublicNavigationItems;
use App\Domain\Content\Queries\VisibleHomepageSections;
use App\Domain\Content\Queries\VisibleTestimonials;
use App\Domain\Content\Support\PublicContentCache;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class PublicContentCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_common_public_configuration_queries_are_cached_and_invalidated_on_change(): void
    {
        $navigation = NavigationItem::query()->create(['label' => 'Walks', 'url' => '/walks', 'enabled' => true, 'sort_order' => 1]);
        $footer = FooterSection::query()->create(['section_key' => 'explore', 'heading' => 'Explore', 'links' => [['label' => 'Walks', 'url' => '/walks']], 'enabled' => true, 'sort_order' => 1]);

        $this->assertSame('Walks', app(PublicNavigationItems::class)->get()->sole()->label);
        $this->assertSame('Explore', app(PublicFooterSections::class)->get()->sole()->heading);
        $this->assertTrue(Cache::has(PublicContentCache::NAVIGATION));
        $this->assertTrue(Cache::has(PublicContentCache::FOOTER));

        $navigation->update(['label' => 'Our walks']);
        $footer->update(['heading' => 'Discover']);

        $this->assertFalse(Cache::has(PublicContentCache::NAVIGATION));
        $this->assertFalse(Cache::has(PublicContentCache::FOOTER));
        $this->assertSame('Our walks', app(PublicNavigationItems::class)->get()->sole()->label);
        $this->assertSame('Discover', app(PublicFooterSections::class)->get()->sole()->heading);
    }

    public function test_branding_and_homepage_queries_are_cached_and_invalidated_on_change(): void
    {
        $profile = SiteProfile::query()->create(['group_name' => 'Waymark Community']);
        $section = HomepageSection::query()->create([
            'section_key' => 'hero',
            'enabled' => true,
            'sort_order' => 1,
            'layout_variant' => 'default',
            'content_mode' => 'automatic',
            'empty_behavior' => 'hide',
        ]);
        $testimonial = Testimonial::query()->create(['quote' => 'A welcoming group.', 'display_name' => 'Alex', 'active' => true]);

        app(PublicBranding::class)->get();
        app(VisibleHomepageSections::class)->get();
        app(VisibleTestimonials::class)->get();

        $this->assertTrue(Cache::has(PublicContentCache::BRANDING));
        $this->assertTrue(Cache::has(PublicContentCache::HOMEPAGE_SECTIONS));
        $this->assertTrue(Cache::has(PublicContentCache::TESTIMONIALS));

        $profile->update(['group_name' => 'Updated walking group']);
        $section->update(['heading' => 'Explore together']);
        $testimonial->update(['quote' => 'A very welcoming group.']);

        $this->assertFalse(Cache::has(PublicContentCache::BRANDING));
        $this->assertFalse(Cache::has(PublicContentCache::HOMEPAGE_SECTIONS));
        $this->assertFalse(Cache::has(PublicContentCache::TESTIMONIALS));
    }

    public function test_public_content_cache_is_safe_with_production_file_serialization_rules(): void
    {
        $cachePath = storage_path('framework/testing/public-content-cache-'.bin2hex(random_bytes(8)));
        File::ensureDirectoryExists($cachePath);
        config()->set('cache.default', 'file');
        config()->set('cache.stores.file.path', $cachePath);
        config()->set('cache.serializable_classes', false);
        app('cache')->forgetDriver('file');

        try {
            NavigationItem::query()->create(['label' => 'Walks', 'url' => '/walks', 'enabled' => true, 'sort_order' => 1]);
            FooterSection::query()->create(['section_key' => 'explore', 'heading' => 'Explore', 'links' => [], 'enabled' => true, 'sort_order' => 1]);
            HomepageSection::query()->create(['section_key' => 'hero', 'enabled' => true, 'sort_order' => 1, 'layout_variant' => 'default', 'content_mode' => 'automatic', 'empty_behavior' => 'hide']);
            $author = User::factory()->create();
            $media = SiteMedia::query()->create([
                'created_by_user_id' => $author->id,
                'storage_key' => '3f2504e0-4f89-41d3-9a0c-0305e82c3300',
                'storage_disk' => 'local',
                'processed_variants' => [],
                'mime_type' => 'image/jpeg',
                'width' => 1200,
                'height' => 800,
                'file_size_bytes' => 5,
                'alt_text' => 'Walkers on a ridge',
                'focal_point_x' => .5,
                'focal_point_y' => .5,
                'processing_status' => 'pending',
                'health_status' => 'pending',
            ]);
            Testimonial::query()->create(['quote' => 'A welcoming group.', 'display_name' => 'Alex', 'image_media_id' => $media->id, 'active' => true]);

            app(PublicNavigationItems::class)->get();
            app(PublicFooterSections::class)->get();
            app(VisibleHomepageSections::class)->get();
            app(VisibleTestimonials::class)->get();

            $this->assertInstanceOf(NavigationItem::class, app(PublicNavigationItems::class)->get()->sole());
            $this->assertInstanceOf(FooterSection::class, app(PublicFooterSections::class)->get()->sole());
            $this->assertInstanceOf(HomepageSection::class, app(VisibleHomepageSections::class)->get()->sole());
            $testimonial = app(VisibleTestimonials::class)->get()->sole();
            $this->assertInstanceOf(Testimonial::class, $testimonial);
            $this->assertInstanceOf(SiteMedia::class, $testimonial->imageMedia);
        } finally {
            app('cache')->forgetDriver('file');
            File::deleteDirectory($cachePath);
        }
    }
}
