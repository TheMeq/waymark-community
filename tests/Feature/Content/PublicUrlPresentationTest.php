<?php

namespace Tests\Feature\Content;

use App\Domain\Content\Models\FooterSection;
use App\Domain\Content\Models\HomepageSection;
use App\Domain\Content\Models\NavigationItem;
use App\Domain\Content\Presentation\PublicUrl;
use App\Domain\Content\Queries\PublicBranding;
use App\Domain\Content\Queries\PublicFooterSections;
use App\Domain\Content\Queries\PublicNavigationItems;
use App\Domain\Content\Queries\VisibleHomepageSections;
use App\Domain\Content\Support\CmsBlockPresenter;
use App\Domain\Operations\Actions\UpdateSiteProfile;
use App\ViewModels\HomepageViewModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class PublicUrlPresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        URL::forceRootUrl('https://example.org/demo-site/ndwg');
        URL::forceScheme('https');
    }

    public function test_internal_content_urls_are_resolved_beneath_the_application_prefix(): void
    {
        NavigationItem::query()->create(['label' => 'Walks', 'url' => '/walks', 'sort_order' => 10, 'enabled' => true]);
        FooterSection::query()->create([
            'section_key' => 'legal',
            'heading' => 'Legal',
            'sort_order' => 10,
            'enabled' => true,
            'links' => [['label' => 'Privacy', 'url' => '/policies/privacy']],
        ]);
        HomepageSection::query()->create([
            'section_key' => 'hero',
            'enabled' => true,
            'sort_order' => 10,
            'layout_variant' => 'default',
            'content_mode' => 'automatic',
            'empty_behavior' => 'hide',
            'cta_url' => '/walks?featured=1',
        ]);

        $this->assertSame('/demo-site/ndwg/walks', app(PublicNavigationItems::class)->get()->sole()->url);
        $this->assertSame('/demo-site/ndwg/policies/privacy', app(PublicFooterSections::class)->get()->sole()->links[0]['url']);
        $this->assertSame('/demo-site/ndwg/walks?featured=1', app(VisibleHomepageSections::class)->get()->sole()->cta_url);
        $this->assertSame('/demo-site/ndwg/contact', PublicUrl::resolve('/contact'));
        $this->assertSame('https://external.example/contact', PublicUrl::resolve('https://external.example/contact'));
    }

    public function test_branding_cms_links_and_demo_assets_are_prefix_aware(): void
    {
        app(UpdateSiteProfile::class)->handle([
            'group_name' => 'Prefix Walkers',
            'logo_path' => '/images/demo/waymark-logo.svg',
            'favicon_path' => '/images/demo/favicon.png',
            'hero_default_path' => '/images/demo/hero-walkers.png',
            'social_links' => ['Local guide' => '/documents'],
            'affiliation_name' => 'Partner',
            'affiliation_url' => '/pages/partner',
        ]);

        $branding = app(PublicBranding::class)->get();
        $this->assertSame('/demo-site/ndwg/images/demo/waymark-logo.svg', $branding['logo_url']);
        $this->assertSame('/demo-site/ndwg/images/demo/favicon.png', $branding['favicon_url']);
        $this->assertSame('/demo-site/ndwg/images/demo/hero-walkers.png', $branding['hero_url']);
        $this->assertSame('/demo-site/ndwg/documents', $branding['social_links'][0]['url']);
        $this->assertSame('/demo-site/ndwg/pages/partner', $branding['affiliation_url']);

        $blocks = app(CmsBlockPresenter::class)->present([[
            'type' => 'document_list',
            'items' => [['label' => 'Join us', 'url' => '/new-here']],
        ]]);
        $this->assertSame('/demo-site/ndwg/new-here', $blocks[0]['items'][0]['url']);

        $homepage = HomepageViewModel::demo([], [], []);
        $this->assertSame('/demo-site/ndwg/images/demo/hero-walkers-1536.webp', $homepage->hero['image_url']);
        $this->assertStringContainsString('/demo-site/ndwg/images/demo/hero-walkers-768.webp 768w', $homepage->hero['image_srcset']);
    }
}
