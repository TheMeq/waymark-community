<?php

namespace Tests\Feature\Public;

use App\Domain\Communication\Models\PolicyPage;
use App\Domain\Operations\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PrivacyPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_footer_destination_works_without_a_published_policy(): void
    {
        SiteProfile::query()->create(['id' => 1, 'group_name' => 'Trail Friends']);
        $this->get(route('policies.show', 'privacy'))->assertOk()
            ->assertSee('Privacy')->assertSee('Trail Friends')
            ->assertSee(route('contact.create'), false)
            ->assertSee(route('cookie-settings.edit'), false)
            ->assertSee(route('account.privacy.show'), false);
    }

    public function test_unpublished_policy_content_is_not_exposed_by_the_default_page(): void
    {
        $policy = PolicyPage::query()->create(['review_notice' => '', 'policy_key' => 'privacy', 'slug' => 'privacy', 'title' => 'Privacy']);
        $draft = $policy->versions()->create(['version_number' => 1, 'publication_state' => 'draft', 'body' => 'Private draft wording']);
        $policy->update(['current_version_id' => $draft->id]);
        $this->get(route('policies.show', 'privacy'))->assertOk()->assertDontSee('Private draft wording');
    }

    public function test_published_group_privacy_policy_takes_precedence(): void
    {
        $policy = PolicyPage::query()->create(['review_notice' => '', 'policy_key' => 'privacy', 'slug' => 'privacy', 'title' => 'Our privacy policy']);
        $version = $policy->versions()->create(['version_number' => 1, 'publication_state' => 'published', 'published_at' => now(), 'body' => 'Our approved privacy wording']);
        $policy->update(['current_version_id' => $version->id]);
        $this->get(route('policies.show', 'privacy'))->assertOk()->assertViewIs('policies.show')->assertSee('Our approved privacy wording');
    }

    public function test_other_missing_policies_still_return_not_found(): void
    {
        $this->get(route('policies.show', 'unknown-policy'))->assertNotFound();
    }
}
