<?php

namespace Tests\Feature\Content;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Content\Models\FooterSection;
use App\Domain\Content\Models\NavigationItem;
use App\Domain\Content\Queries\PublicFooterSections;
use App\Domain\Content\Queries\PublicNavigationItems;
use App\Domain\Operations\Actions\CreateBrandingPreview;
use App\Domain\Operations\Actions\UpdateSiteProfile;
use App\Domain\Operations\Models\BrandingConfigurationSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class NavigationBrandingTest extends TestCase
{
    use RefreshDatabase;

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
            'logo_path' => '/images/demo/waymark-logo.svg',
            'favicon_path' => '/images/demo/favicon.png',
            'hero_default_path' => '/images/demo/hero-walkers.png',
            'social_links' => ['Instagram' => 'https://instagram.com/peak-pathfinders'],
            'terminology' => ['walks' => 'Rambles', 'members' => 'Community', 'join' => 'Join our circle'],
            'affiliation_name' => 'County Walking Network',
            'affiliation_url' => 'https://example.org/network',
        ], $administrator);

        $this->actingAs($administrator)->get('/admin/branding')->assertOk()
            ->assertSeeText('Group name')->assertSeeText('Social links')->assertSeeText('Terminology aliases')
            ->assertSeeText('Affiliation name')->assertDontSeeText('Custom CSS');

        $this->get('/')->assertOk()
            ->assertSeeText('Peak Pathfinders')->assertSeeText('Rambles')->assertSeeText('Community')->assertSeeText('Join our circle')
            ->assertSeeText('Instagram')->assertSee('https://instagram.com/peak-pathfinders', false)
            ->assertSeeText('County Walking Network')->assertSee('href="/images/demo/favicon.png"', false);
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
}
