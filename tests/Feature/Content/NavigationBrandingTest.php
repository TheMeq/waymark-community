<?php

namespace Tests\Feature\Content;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Content\Models\FooterSection;
use App\Domain\Content\Models\NavigationItem;
use App\Domain\Content\Queries\PublicFooterSections;
use App\Domain\Content\Queries\PublicNavigationItems;
use App\Domain\Operations\Actions\CreateBrandingPreview;
use App\Domain\Operations\Actions\UpdateBrandingImage;
use App\Domain\Operations\Actions\UpdateSiteProfile;
use App\Domain\Operations\Data\BrandingImageInput;
use App\Domain\Operations\Models\BrandingConfigurationSnapshot;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\SiteMedia\Enums\SiteMediaPurpose;
use App\Filament\Pages\BrandingSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class NavigationBrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config()->set('gallery.photos.disk', 'local');
    }

    public function test_navigation_is_flat_guardrailed_ordered_and_module_aware(): void
    {
        app(UpdateSiteProfile::class)->handle(['group_name' => 'Trail Friends', 'module_configuration' => ['gallery' => false]]);
        NavigationItem::query()->create(['label' => 'Contact', 'url' => '/contact', 'sort_order' => 30, 'enabled' => true]);
        NavigationItem::query()->create(['label' => 'Walks', 'url' => '/walks', 'sort_order' => 10, 'enabled' => true, 'module_key' => 'walks']);
        NavigationItem::query()->create(['label' => 'Gallery', 'url' => '/photos', 'sort_order' => 20, 'enabled' => true, 'module_key' => 'gallery']);

        $this->assertSame(['Walks', 'Contact'], app(PublicNavigationItems::class)->get()->pluck('label')->all());

        $this->expectException(ValidationException::class);
        NavigationItem::query()->create(['label' => 'Unsafe', 'url' => 'javascript:alert(1)', 'sort_order' => 40, 'enabled' => true]);
    }

    public function test_footer_is_curated_and_ordered(): void
    {
        FooterSection::query()->create(['section_key' => 'legal', 'heading' => 'Legal', 'sort_order' => 20, 'enabled' => true, 'links' => [['label' => 'Privacy', 'url' => '/privacy']]]);
        FooterSection::query()->create(['section_key' => 'contact', 'heading' => 'Contact', 'sort_order' => 10, 'enabled' => true, 'links' => [['label' => 'Get in touch', 'url' => '/contact']]]);

        $this->assertSame(['contact', 'legal'], app(PublicFooterSections::class)->get()->pluck('section_key')->all());

        $this->expectException(ValidationException::class);
        FooterSection::query()->create(['section_key' => 'freeform_html', 'sort_order' => 30, 'enabled' => true, 'links' => []]);
    }

    public function test_branding_updates_are_snapshotted_and_safe_foregrounds_are_previewed_without_saving(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $profile = app(UpdateSiteProfile::class)->handle(['group_name' => 'Trail Friends', 'primary_colour' => '#526B3F']);
        app(UpdateSiteProfile::class)->handle(['primary_colour' => '#F9E547', 'accent_colour' => '#173B2D', 'typography_option' => 'instrument'], $administrator);

        $snapshot = BrandingConfigurationSnapshot::query()->sole();
        $this->assertSame('#526B3F', $snapshot->previous_values['primary_colour']);
        $this->assertSame('#F9E547', $snapshot->new_values['primary_colour']);

        $preview = app(CreateBrandingPreview::class)->handle($administrator, ['primary_colour' => '#000000', 'accent_colour' => '#FFFFFF']);
        $this->actingAs($administrator)->get(route('branding.preview', ['token' => $preview->token, 'viewport' => 'mobile']))
            ->assertOk()->assertSee('--wm-brand: #000000', false)->assertSeeText('Mobile preview');
        $this->assertSame('#F9E547', $profile->fresh()->primary_colour);
    }

    public function test_navigation_footer_and_branding_admin_are_content_manager_only(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $member = User::factory()->create(['role' => AccountRole::RegisteredUser, 'email_verified_at' => now()]);

        foreach (['/admin/navigation-items', '/admin/footer-sections', '/admin/branding'] as $path) {
            $this->actingAs($administrator)->get($path)->assertOk();
            $this->actingAs($member)->get($path)->assertForbidden();
        }
    }

    public function test_approved_identity_social_affiliation_and_terminology_settings_are_exposed_and_presented(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        app(UpdateSiteProfile::class)->handle([
            'group_name' => 'Peak Pathfinders',
            'short_name' => 'PP',
            'contact_email' => 'hello@example.org',
            'hero_default_path' => '/images/demo/hero-walkers.png',
            'social_links' => ['Instagram' => 'https://instagram.com/peak-pathfinders'],
            'terminology' => ['walks' => 'Rambles', 'members' => 'Community', 'join' => 'Join our circle'],
            'affiliation_name' => 'County Walking Network',
            'affiliation_url' => 'https://example.org/network',
        ], $administrator);
        $profile = SiteProfile::query()->findOrFail(SiteProfile::SINGLETON_ID);
        app(UpdateBrandingImage::class)->handle(
            $administrator,
            $profile,
            SiteMediaPurpose::SiteLogo,
            BrandingImageInput::from(['logo_source' => 'external', 'logo_path' => '/images/demo/waymark-logo.svg'], SiteMediaPurpose::SiteLogo),
        );
        app(UpdateBrandingImage::class)->handle(
            $administrator,
            $profile->fresh(),
            SiteMediaPurpose::SiteFavicon,
            BrandingImageInput::from(['favicon_source' => 'external', 'favicon_path' => '/images/demo/favicon.png'], SiteMediaPurpose::SiteFavicon),
        );

        $this->actingAs($administrator)->get('/admin/branding')->assertOk()
            ->assertSeeText('Group name')->assertSeeText('Social links')->assertSeeText('Terminology aliases')
            ->assertSeeText('Affiliation name')->assertDontSeeText('Custom CSS');

        $this->get('/')->assertOk()
            ->assertSeeText('Peak Pathfinders')->assertSeeText('Rambles')->assertSeeText('Community')->assertSeeText('Join our circle')
            ->assertSeeText('Instagram')->assertSee('https://instagram.com/peak-pathfinders', false)
            ->assertSeeText('County Walking Network')->assertSee('href="/images/demo/favicon.png"', false);
    }

    public function test_managed_branding_renders_one_resolved_logo_in_header_footer_and_seo_and_png_favicon_without_changing_pwa_icons(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $profile = app(UpdateSiteProfile::class)->handle(['group_name' => 'Managed Pathfinders']);
        $profile = app(UpdateBrandingImage::class)->handle(
            $administrator,
            $profile,
            SiteMediaPurpose::SiteLogo,
            BrandingImageInput::from([
                'logo_source' => 'managed',
                'logo_upload' => UploadedFile::fake()->image('logo.png', 80, 60),
            ], SiteMediaPurpose::SiteLogo),
        );
        $profile = app(UpdateBrandingImage::class)->handle(
            $administrator,
            $profile,
            SiteMediaPurpose::SiteFavicon,
            BrandingImageInput::from([
                'favicon_source' => 'managed',
                'favicon_upload' => UploadedFile::fake()->image('favicon.png', 64, 64),
            ], SiteMediaPurpose::SiteFavicon),
        );
        $logoUrl = route('site-media.stream', [$profile->logoMedia, 'medium']);
        $faviconUrl = route('site-media.stream', [$profile->faviconMedia, 'favicon']);

        $content = $this->get('/')->assertOk()->getContent();
        $this->assertIsString($content);
        $this->assertSame(2, substr_count($content, 'src="'.$logoUrl.'" alt=""'));
        $this->assertGreaterThanOrEqual(2, substr_count($content, 'Managed Pathfinders'));
        $this->assertStringContainsString('aria-label="Managed Pathfinders home"', $content);
        $this->assertStringContainsString('<link rel="icon" href="'.$faviconUrl.'" type="image/png">', $content);
        $this->assertStringContainsString('"logo":"'.$logoUrl.'"', $content);

        $this->get('/manifest.webmanifest')
            ->assertOk()
            ->assertJsonPath('icons.0.src', '/images/pwa/icon-192.png')
            ->assertJsonPath('icons.1.src', '/images/pwa/icon-512.png');
    }

    public function test_branding_preview_keeps_saved_managed_logo_while_previewing_unsaved_ordinary_values(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $profile = app(UpdateSiteProfile::class)->handle(['group_name' => 'Saved Walkers']);
        $profile = app(UpdateBrandingImage::class)->handle(
            $administrator,
            $profile,
            SiteMediaPurpose::SiteLogo,
            BrandingImageInput::from([
                'logo_source' => 'managed',
                'logo_upload' => UploadedFile::fake()->image('logo.png', 80, 60),
            ], SiteMediaPurpose::SiteLogo),
        );
        $logoUrl = route('site-media.stream', [$profile->logoMedia, 'medium']);

        $preview = app(CreateBrandingPreview::class)->handle($administrator, ['group_name' => 'Unsaved Pathfinders']);

        $this->actingAs($administrator)->get(route('branding.preview', ['token' => $preview->token, 'viewport' => 'desktop']))
            ->assertOk()
            ->assertSeeText('Unsaved Pathfinders')
            ->assertSee('src="'.$logoUrl.'" alt=""', false);

        $externalPreview = app(CreateBrandingPreview::class)->handle($administrator, [
            'group_name' => 'External Preview',
            'logo_source' => 'external',
            'logo_path' => 'https://images.example.org/preview-logo.png',
        ]);
        $this->actingAs($administrator)->get(route('branding.preview', ['token' => $externalPreview->token, 'viewport' => 'desktop']))
            ->assertOk()
            ->assertSee('src="https://images.example.org/preview-logo.png" alt=""', false)
            ->assertDontSee('src="'.$logoUrl.'" alt=""', false);
    }

    public function test_fallback_public_links_use_current_named_routes(): void
    {
        $content = $this->get('/')->assertOk()->getContent();
        $this->assertIsString($content);

        foreach ([route('walks.index'), route('events.index'), route('holidays.index'), route('gallery.index'), route('contact.create'), route('policies.show', 'privacy'), route('policies.show', 'accessibility')] as $url) {
            $this->assertStringContainsString($url, $content);
        }
        foreach (['/account"', '/walk-leaders', 'href="/privacy"', 'href="/accessibility"'] as $stale) {
            $this->assertStringNotContainsString($stale, $content);
        }
    }

    public function test_branding_rejects_unapproved_terminology_and_unsafe_public_links(): void
    {
        $this->expectException(ValidationException::class);
        app(UpdateSiteProfile::class)->handle([
            'terminology' => ['internal_model_name' => 'Something else'],
            'social_links' => ['Unsafe' => 'javascript:alert(1)'],
        ]);
    }

    public function test_branding_page_previews_unsaved_values_at_each_viewport_without_saving(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $otherAdministrator = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        app(UpdateSiteProfile::class)->handle([
            'group_name' => 'Saved Walkers',
            'primary_colour' => '#526B3F',
            'accent_colour' => '#D6B269',
            'typography_option' => 'instrument',
        ]);

        $this->actingAs($administrator);
        $component = Livewire::test(BrandingSettings::class)
            ->fillForm([
                'group_name' => 'Unsaved Pathfinders',
                'primary_colour' => '#000000',
                'accent_colour' => '#FFFFFF',
                'logo_path' => '/images/demo/waymark-logo.svg',
            ])
            ->call('preview')
            ->assertHasNoErrors();

        $links = $component->get('previewLinks');
        $this->assertIsArray($links);
        $this->assertSame(['desktop', 'tablet', 'mobile'], array_keys($links));

        parse_str((string) parse_url($links['desktop'], PHP_URL_QUERY), $desktopQuery);
        $token = $desktopQuery['token'];
        $this->assertSame('Unsaved Pathfinders', Cache::store('file')->get('branding-preview:'.$token)['values']['group_name']);

        foreach ($links as $viewport => $url) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $this->assertSame($token, $query['token']);
            $this->assertSame($viewport, $query['viewport']);
            $this->actingAs($administrator)->get($url)
                ->assertOk()
                ->assertSeeText(ucfirst($viewport).' preview')
                ->assertSeeText('Unsaved Pathfinders');
        }

        $profile = SiteProfile::query()->findOrFail(SiteProfile::SINGLETON_ID);
        $this->assertSame('Saved Walkers', $profile->group_name);
        $this->assertSame('#526B3F', $profile->primary_colour);

        $this->actingAs($otherAdministrator)->get($links['desktop'])->assertNotFound();
        $this->travel(31)->minutes();
        $this->actingAs($administrator)->get($links['desktop'])->assertNotFound();
    }

    public function test_branding_preview_rejects_unsafe_unsaved_public_values(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);

        $this->expectException(ValidationException::class);
        app(CreateBrandingPreview::class)->handle($administrator, [
            'group_name' => 'Unsafe preview',
            'logo_path' => 'javascript:alert(1)',
        ]);
    }

    public function test_branding_editor_shows_concise_guidance_only_for_weak_contrast(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        app(UpdateSiteProfile::class)->handle(['group_name' => 'Trail Friends', 'typography_option' => 'instrument']);

        $this->actingAs($administrator);
        Livewire::test(BrandingSettings::class)
            ->set('data.primary_colour', '#000000')
            ->assertDontSee('Contrast may be weak in some components.')
            ->set('data.primary_colour', '#777777')
            ->assertSee('Contrast may be weak in some components.');
    }
}
