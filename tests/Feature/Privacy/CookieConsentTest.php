<?php

namespace Tests\Feature\Privacy;

use App\Domain\Communication\Models\PolicyConsent;
use App\Domain\Communication\Models\PolicyPage;
use App\Domain\Communication\Models\PolicyVersion;
use App\Models\User;
use Database\Seeders\PolicyStarterTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CookieConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_first_visit_offers_clear_essential_and_optional_cookie_choices(): void
    {
        $this->get('/')->assertOk()
            ->assertSeeText('Cookie choices')
            ->assertSeeText('Use essential only')
            ->assertSeeText('Allow analytics')
            ->assertSee(route('cookie-settings.edit'), false);
    }

    public function test_cookie_preferences_can_be_saved_and_revisited(): void
    {
        $response = $this->from('/cookie-settings')->post('/cookie-settings', ['analytics' => '1']);

        $response->assertRedirect('/cookie-settings')->assertCookie('waymark_consent');
        $this->withCookie('waymark_consent', json_encode(['essential' => true, 'analytics' => true, 'version' => 1]))
            ->get('/cookie-settings')->assertOk()->assertSee('name="analytics"', false)->assertSee('checked', false);
    }

    public function test_authenticated_analytics_consent_is_linked_to_the_current_cookie_policy_version(): void
    {
        $policy = $this->publishedCookiePolicy();
        $user = User::factory()->create();

        $this->actingAs($user)->post('/cookie-settings', ['analytics' => '1'])->assertRedirect();

        $this->assertDatabaseHas('policy_consents', [
            'user_id' => $user->id,
            'policy_version_id' => $policy->id,
            'action' => 'analytics',
            'withdrawn_at' => null,
        ]);

        $this->actingAs($user)->post('/cookie-settings', ['analytics' => '0']);
        $this->assertNotNull(PolicyConsent::query()->where('user_id', $user->id)->where('action', 'analytics')->firstOrFail()->withdrawn_at);
    }

    public function test_anonymous_cookie_preferences_do_not_create_tracking_records(): void
    {
        $this->publishedCookiePolicy();

        $this->post('/cookie-settings', ['analytics' => '1'])->assertRedirect();

        $this->assertDatabaseCount('policy_consents', 0);
    }

    public function test_starter_templates_are_drafts_flagged_for_review_and_cover_accessibility_requirements(): void
    {
        $this->seed(PolicyStarterTemplateSeeder::class);

        $this->assertDatabaseCount('policy_pages', 5);
        $this->assertTrue(PolicyPage::query()->get()->every(fn (PolicyPage $page): bool => str_contains($page->review_notice, 'not legal advice')));
        $this->assertTrue(PolicyVersion::query()->get()->every(fn (PolicyVersion $version): bool => $version->publication_state === 'draft'));
        $accessibility = PolicyPage::query()->where('policy_key', 'accessibility')->firstOrFail()->versions()->firstOrFail();
        $this->assertStringContainsString('WCAG 2.2 AA', $accessibility->body);
        $this->assertStringContainsString('known limitations', mb_strtolower($accessibility->body));
        $this->assertStringContainsString('accessibility contact', mb_strtolower($accessibility->body));
    }

    private function publishedCookiePolicy(): PolicyVersion
    {
        $page = PolicyPage::query()->create(['policy_key' => 'cookies', 'title' => 'Cookie Policy', 'slug' => 'cookies', 'review_notice' => 'Reviewed.']);
        $version = $page->versions()->create(['version_number' => 2, 'body' => 'Cookie policy.', 'publication_state' => 'published', 'published_at' => now()]);
        $page->update(['current_version_id' => $version->id]);

        return $version;
    }
}
