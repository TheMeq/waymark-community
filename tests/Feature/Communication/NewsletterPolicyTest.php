<?php

namespace Tests\Feature\Communication;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Models\CommunicationPreference;
use App\Domain\Communication\Actions\RecordPolicyConsent;
use App\Domain\Communication\Actions\PublishPolicyVersion;
use App\Domain\Communication\Actions\SendDueNewsletters;
use App\Domain\Communication\Actions\SendNewsletter;
use App\Domain\Communication\Mail\NewsletterMail;
use App\Domain\Communication\Models\EmailTemplate;
use App\Domain\Communication\Models\Newsletter;
use App\Domain\Communication\Models\PolicyConsent;
use App\Domain\Communication\Models\PolicyPage;
use App\Domain\Communication\Models\PolicyVersion;
use App\Domain\Communication\Queries\NewsletterAudience;
use App\Models\User;
use Database\Seeders\PolicyStarterTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class NewsletterPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_consent_links_exact_version_timestamp_user_and_action(): void
    {
        $user = User::factory()->create();
        $version = $this->newsletterPolicy();

        app(RecordPolicyConsent::class)->handle($user, $version, 'newsletter');

        $consent = PolicyConsent::query()->sole();
        $this->assertSame($user->id, $consent->user_id);
        $this->assertSame($version->id, $consent->policy_version_id);
        $this->assertSame('newsletter', $consent->action);
        $this->assertNotNull($consent->accepted_at);
    }

    public function test_newsletter_audience_requires_role_status_opt_in_and_current_policy_consent(): void
    {
        $policy = $this->newsletterPolicy();
        $eligible = $this->subscribedUser(AccountRole::VerifiedMember, $policy);
        $wrongRole = $this->subscribedUser(AccountRole::RegisteredUser, $policy);
        $withoutConsent = User::factory()->create(['role' => AccountRole::VerifiedMember]);
        $withoutConsent->communicationPreferences()->create(['category' => 'group_news', 'is_subscribed' => true, 'consented_at' => now()]);
        $newsletter = Newsletter::query()->create($this->newsletter(['audience_roles' => [AccountRole::VerifiedMember->value], 'consent_policy_version_id' => $policy->id]));

        $this->assertSame([$eligible->id], app(NewsletterAudience::class)->for($newsletter)->pluck('id')->all());
        $this->assertNotContains($wrongRole->id, app(NewsletterAudience::class)->for($newsletter)->pluck('id')->all());
    }

    public function test_newsletter_can_send_now_or_when_due_without_management_queue(): void
    {
        Mail::fake();
        $policy = $this->newsletterPolicy();
        $recipient = $this->subscribedUser(AccountRole::VerifiedMember, $policy);
        $actor = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $now = Newsletter::query()->create($this->newsletter(['consent_policy_version_id' => $policy->id]));
        $scheduled = Newsletter::query()->create($this->newsletter(['title' => 'Scheduled', 'status' => 'scheduled', 'scheduled_for' => now()->subMinute(), 'consent_policy_version_id' => $policy->id]));

        $this->assertSame(1, app(SendNewsletter::class)->handle($actor, $now));
        $this->assertSame(1, app(SendDueNewsletters::class)->handle($actor));
        Mail::assertSent(NewsletterMail::class, 2);
        Mail::assertSent(NewsletterMail::class, fn (NewsletterMail $mail): bool => $mail->hasTo($recipient->email));
        $this->assertSame('sent', $now->fresh()->status);
        $this->assertSame('sent', $scheduled->fresh()->status);
    }

    public function test_due_newsletters_can_be_sent_by_the_shared_hosting_scheduler_command(): void
    {
        Mail::fake();
        $policy = $this->newsletterPolicy();
        $this->subscribedUser(AccountRole::VerifiedMember, $policy);
        User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $newsletter = Newsletter::query()->create($this->newsletter([
            'status' => 'scheduled',
            'scheduled_for' => now()->subMinute(),
            'consent_policy_version_id' => $policy->id,
        ]));

        $this->assertSame(0, Artisan::call('communications:send-due-newsletters'));
        $this->assertSame('sent', $newsletter->fresh()->status);
        Mail::assertSent(NewsletterMail::class, 1);
    }

    public function test_email_templates_are_editable_wording_not_arbitrary_html(): void
    {
        EmailTemplate::query()->create(['template_key' => 'account_welcome', 'subject' => 'Welcome to {{ group_name }}', 'intro_text' => 'Thanks for joining.', 'action_label' => 'View your account', 'closing_text' => 'See you outside.']);
        $this->assertDatabaseHas('email_templates', ['template_key' => 'account_welcome']);

        $this->expectException(ValidationException::class);
        EmailTemplate::query()->create(['template_key' => 'unsafe', 'subject' => 'Unsafe', 'intro_text' => '<script>alert(1)</script>']);
    }

    public function test_starter_policies_are_drafts_clearly_marked_for_group_and_legal_review(): void
    {
        $this->seed(PolicyStarterTemplateSeeder::class);
        $this->assertSame(5, PolicyPage::query()->count());
        $this->assertTrue(PolicyPage::query()->get()->every(fn (PolicyPage $page): bool => str_contains($page->review_notice, 'not legal advice')));
        $this->assertTrue(PolicyVersion::query()->get()->every(fn (PolicyVersion $version): bool => $version->publication_state === 'draft'));
    }

    public function test_published_policy_page_renders_its_current_version_and_review_notice(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $page = PolicyPage::query()->create(['policy_key' => 'privacy', 'title' => 'Privacy Policy', 'slug' => 'privacy', 'review_notice' => 'Reviewed by the group.']);
        $version = $page->versions()->create(['version_number' => 1, 'body' => 'We retain contact submissions briefly.', 'publication_state' => 'draft']);

        app(PublishPolicyVersion::class)->handle($administrator, $version);

        $this->get('/policies/privacy')->assertOk()->assertSeeText('We retain contact submissions briefly.')->assertSeeText('Reviewed by the group.');
        $this->assertSame($version->id, $page->fresh()->current_version_id);
    }

    public function test_communications_and_policy_admin_are_manager_only(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $member = User::factory()->create(['role' => AccountRole::RegisteredUser, 'email_verified_at' => now()]);
        foreach (['/admin/newsletters', '/admin/email-templates', '/admin/policy-pages'] as $path) {
            $this->actingAs($administrator)->get($path)->assertOk();
            $this->actingAs($member)->get($path)->assertForbidden();
        }
    }

    private function newsletterPolicy(): PolicyVersion
    {
        $page = PolicyPage::query()->create(['policy_key' => 'newsletter_consent', 'title' => 'Newsletter consent', 'slug' => 'newsletter-consent', 'review_notice' => 'Review before use.']);
        return $page->versions()->create(['version_number' => 1, 'body' => 'I agree to group news by email.', 'publication_state' => 'published', 'published_at' => now()]);
    }

    private function subscribedUser(AccountRole $role, PolicyVersion $policy): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->communicationPreferences()->create(['category' => 'group_news', 'is_subscribed' => true, 'consented_at' => now()]);
        app(RecordPolicyConsent::class)->handle($user, $policy, 'newsletter');
        return $user;
    }

    /** @param array<string, mixed> $overrides */
    private function newsletter(array $overrides = []): array
    {
        return array_merge(['title' => 'August update', 'subject' => 'Group news', 'body' => 'A concise update from the group.', 'status' => 'draft', 'audience_roles' => [AccountRole::VerifiedMember->value], 'audience_account_statuses' => ['active'], 'consent_required' => true], $overrides);
    }
}
