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
}
