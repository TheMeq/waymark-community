<?php

namespace Tests\Feature\Communication;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Communication\Actions\PurgeExpiredContactSubmissions;
use App\Domain\Communication\Mail\ContactSubmissionMail;
use App\Domain\Communication\Models\ContactDepartment;
use App\Domain\Communication\Models\ContactSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_form_routes_by_department_without_exposing_private_address(): void
    {
        Mail::fake();
        $department = ContactDepartment::query()->create(['public_label' => 'Walks', 'destination_email' => 'walks-private@example.org', 'description' => 'Questions about walks.', 'active' => true, 'sort_order' => 10]);

        $this->get('/contact')->assertOk()->assertSeeText('Walks')->assertDontSee('walks-private@example.org');
        $this->post('/contact', ['contact_department_id' => $department->id, 'name' => 'Taylor', 'email' => 'taylor@example.net', 'message' => 'Could you help with the grading guide?', 'website' => ''])->assertRedirect('/contact');

        Mail::assertSent(ContactSubmissionMail::class, fn (ContactSubmissionMail $mail): bool => $mail->hasTo('walks-private@example.org'));
        $submission = ContactSubmission::query()->sole();
        $this->assertSame($department->id, $submission->contact_department_id);
        $this->assertTrue($submission->retention_expires_at->isFuture());
    }

    public function test_department_keyword_rule_can_route_to_a_private_specialist_address(): void
    {
        Mail::fake();
        $department = ContactDepartment::query()->create([
            'public_label' => 'Walks',
            'destination_email' => 'walks-private@example.org',
            'routing_rules' => ['accessibility' => 'access-private@example.org'],
            'active' => true,
            'sort_order' => 10,
        ]);

        $this->post('/contact', [
            'contact_department_id' => $department->id,
            'name' => 'Taylor',
            'email' => 'taylor@example.net',
            'message' => 'I have an accessibility question about a walk.',
            'website' => '',
        ])->assertRedirect('/contact');

        Mail::assertSent(ContactSubmissionMail::class, fn (ContactSubmissionMail $mail): bool => $mail->hasTo('access-private@example.org'));
        $this->get('/contact')->assertDontSee('access-private@example.org');
    }

    public function test_honeypot_and_inactive_department_are_rejected(): void
    {
        Mail::fake();
        $department = ContactDepartment::query()->create(['public_label' => 'General', 'destination_email' => 'private@example.org', 'active' => false, 'sort_order' => 10]);

        $this->post('/contact', ['contact_department_id' => $department->id, 'name' => 'Bot', 'email' => 'bot@example.net', 'message' => 'Spam', 'website' => 'https://spam.example'])->assertSessionHasErrors();
        Mail::assertNothingSent();
        $this->assertDatabaseCount('contact_submissions', 0);
    }

    public function test_short_term_retention_cleanup_deletes_only_expired_submissions(): void
    {
        $department = ContactDepartment::query()->create(['public_label' => 'General', 'destination_email' => 'private@example.org', 'active' => true, 'sort_order' => 10]);
        $expired = ContactSubmission::query()->create(['contact_department_id' => $department->id, 'name' => 'Old', 'email' => 'old@example.net', 'message' => 'Old message', 'retention_expires_at' => now()->subMinute()]);
        $current = ContactSubmission::query()->create(['contact_department_id' => $department->id, 'name' => 'New', 'email' => 'new@example.net', 'message' => 'New message', 'retention_expires_at' => now()->addDay()]);

        $this->assertSame(1, app(PurgeExpiredContactSubmissions::class)->handle());
        $this->assertModelMissing($expired);
        $this->assertModelExists($current);
        $this->assertFalse(Schema::hasColumns('contact_submissions', ['status', 'assigned_to', 'reply_history']));
    }

    public function test_contact_admin_is_communications_manager_only(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $member = User::factory()->create(['role' => AccountRole::RegisteredUser, 'email_verified_at' => now()]);
        $this->actingAs($administrator)->get('/admin/contact-departments')->assertOk();
        $this->actingAs($member)->get('/admin/contact-departments')->assertForbidden();
    }
}
