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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
}
