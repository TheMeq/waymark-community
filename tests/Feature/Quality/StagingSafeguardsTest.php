<?php

namespace Tests\Feature\Quality;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Operations\Environment\StagingEnvironmentGuard;
use App\Domain\Operations\Models\AnalyticsSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StagingSafeguardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_staging_blocks_indexing_sitemaps_and_optional_analytics(): void
    {
        config()->set('waymark.staging', true);
        AnalyticsSetting::query()->create([
            'singleton_key' => 'public',
            'provider' => 'plausible',
            'tracking_id' => 'walks.example.org',
            'enabled' => true,
        ]);

        $this->withCookie('waymark_consent', json_encode(['essential' => true, 'analytics' => true, 'version' => 1]))
            ->get('/')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
            ->assertDontSee('plausible.io/js/script.js', false)
            ->assertDontSee('walks.example.org', false);

        $this->get('/sitemap.xml')->assertNotFound();
        $this->get('/robots.txt')->assertOk()
            ->assertSee("User-agent: *\nDisallow: /", false)
            ->assertDontSee('Sitemap:');
    }

    public function test_staging_is_visibly_identified_throughout_the_admin_panel(): void
    {
        config()->set('waymark.staging', true);
        $administrator = User::factory()->create([
            'role' => AccountRole::Administrator,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($administrator)
            ->get('/admin')
            ->assertOk()
            ->assertSeeText('STAGING')
            ->assertSeeText('Search indexing and public analytics are disabled.');
    }

    public function test_production_indexing_routes_remain_available_without_a_staging_banner(): void
    {
        config()->set('waymark.staging', false);
        $administrator = User::factory()->create([
            'role' => AccountRole::Administrator,
            'email_verified_at' => now(),
        ]);

        $this->get('/')->assertOk()
            ->assertSee('<meta name="robots" content="index,follow">', false);
        $this->get('/sitemap.xml')->assertOk();
        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: '.url('/sitemap.xml'));
        $this->actingAs($administrator)->get('/admin')->assertOk()->assertDontSee('data-waymark-staging-banner', false);
    }

    public function test_staging_forces_external_email_into_the_local_log_trap(): void
    {
        config()->set('waymark.staging', true);
        config()->set('mail.default', 'smtp');

        app(StagingEnvironmentGuard::class)->apply();

        $this->assertSame('log', config('mail.default'));
    }

    public function test_staging_preserves_an_explicit_array_mail_trap_and_production_mail(): void
    {
        config()->set('waymark.staging', true);
        config()->set('mail.default', 'array');
        app(StagingEnvironmentGuard::class)->apply();
        $this->assertSame('array', config('mail.default'));

        config()->set('waymark.staging', false);
        config()->set('mail.default', 'smtp');
        app(StagingEnvironmentGuard::class)->apply();
        $this->assertSame('smtp', config('mail.default'));
    }
}
