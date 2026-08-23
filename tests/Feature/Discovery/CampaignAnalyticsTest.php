<?php

namespace Tests\Feature\Discovery;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Operations\Models\AnalyticsSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CampaignAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_approved_campaign_parameters_are_preserved_in_the_session(): void
    {
        $this->get('/?utm_source=mail&utm_medium=email&utm_campaign=summer&utm_term=boots&utm_content=hero&gclid=secret&utm_extra=nope')
            ->assertOk()
            ->assertSessionHas('waymark.campaign', [
                'utm_source' => 'mail',
                'utm_medium' => 'email',
                'utm_campaign' => 'summer',
                'utm_term' => 'boots',
                'utm_content' => 'hero',
            ]);
    }

    public function test_optional_analytics_is_absent_without_explicit_consent(): void
    {
        AnalyticsSetting::query()->create(['singleton_key' => 'public', 'provider' => 'plausible', 'tracking_id' => 'walks.example.org', 'enabled' => true]);

        $this->get('/')->assertOk()
            ->assertDontSee('plausible.io/js/script.js', false)
            ->assertDontSee('walks.example.org', false);
    }

    public function test_consented_analytics_uses_only_the_fixed_approved_provider_adapter(): void
    {
        AnalyticsSetting::query()->create(['singleton_key' => 'public', 'provider' => 'ga4', 'tracking_id' => 'G-ABC123XYZ', 'enabled' => true]);

        $this->withCookie('waymark_consent', json_encode(['essential' => true, 'analytics' => true, 'version' => 1]))
            ->get('/')
            ->assertOk()
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-ABC123XYZ', false)
            ->assertSee("gtag('config', \"G-ABC123XYZ\")", false)
            ->assertDontSee('<script>alert(', false);
    }

    public function test_invalid_provider_identifiers_are_rejected(): void
    {
        foreach ([
            ['provider' => 'ga4', 'tracking_id' => '<script>alert(1)</script>'],
            ['provider' => 'plausible', 'tracking_id' => 'https://example.org/path'],
            ['provider' => 'unknown', 'tracking_id' => 'anything'],
        ] as $attributes) {
            try {
                AnalyticsSetting::query()->create(array_merge(['singleton_key' => 'public', 'enabled' => true], $attributes));
                $this->fail('An invalid analytics configuration was accepted.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_analytics_administration_is_content_manager_only(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $member = User::factory()->create(['role' => AccountRole::RegisteredUser, 'email_verified_at' => now()]);

        $this->actingAs($administrator)->get('/admin/analytics-settings')->assertOk()->assertSeeText('Analytics');
        $this->actingAs($member)->get('/admin/analytics-settings')->assertForbidden();
    }
}
