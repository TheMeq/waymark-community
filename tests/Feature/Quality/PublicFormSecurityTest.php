<?php

namespace Tests\Feature\Quality;

use App\Domain\Communication\Models\ContactDepartment;
use App\Domain\Governance\Actions\UploadDocumentVersion;
use App\Domain\Governance\Models\Document;
use App\Domain\Governance\Models\DocumentCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class PublicFormSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_optional_turnstile_blocks_an_unverified_public_submission(): void
    {
        Mail::fake();
        config()->set('waymark.anti_spam.turnstile', ['enabled' => true, 'site_key' => 'public-key', 'secret_key' => 'private-key']);
        Http::fake(['https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => false])]);
        $department = ContactDepartment::query()->create(['name' => 'General', 'public_label' => 'General', 'destination_email' => 'hello@example.test', 'active' => true, 'sort_order' => 1]);

        $values = ['contact_department_id' => $department->id, 'name' => 'Robin', 'email' => 'robin@example.test', 'message' => 'Hello', 'website' => ''];
        $this->from('/contact')->post('/contact', $values)->assertRedirect('/contact')->assertSessionHasErrors('turnstile');
        $this->assertDatabaseCount('contact_submissions', 0);
    }

    public function test_optional_turnstile_allows_a_verified_public_submission(): void
    {
        Mail::fake();
        config()->set('waymark.anti_spam.turnstile', ['enabled' => true, 'site_key' => 'public-key', 'secret_key' => 'private-key']);
        Http::fake(['https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true])]);
        $department = ContactDepartment::query()->create(['public_label' => 'General', 'destination_email' => 'hello@example.test', 'active' => true, 'sort_order' => 1]);

        $this->post('/contact', ['contact_department_id' => $department->id, 'name' => 'Robin', 'email' => 'robin@example.test', 'message' => 'Hello', 'website' => '', 'cf-turnstile-response' => 'verified-token'])->assertRedirect('/contact')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('contact_submissions', 1);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify' && $request['secret'] === 'private-key');
    }

    public function test_turnstile_is_absent_when_disabled_and_uses_only_the_fixed_widget_when_enabled(): void
    {
        ContactDepartment::query()->create(['name' => 'General', 'public_label' => 'General', 'destination_email' => 'hello@example.test', 'active' => true, 'sort_order' => 1]);

        $this->get('/contact')->assertOk()->assertDontSee('challenges.cloudflare.com/turnstile', false);
        config()->set('waymark.anti_spam.turnstile', ['enabled' => true, 'site_key' => 'public-key', 'secret_key' => 'private-key']);
        $this->get('/contact')->assertOk()
            ->assertSee('https://challenges.cloudflare.com/turnstile/v0/api.js', false)
            ->assertSee('data-sitekey="public-key"', false);
    }

    public function test_document_upload_rejects_spoofed_content_and_uses_generated_storage_names(): void
    {
        Storage::fake('local');
        $actor = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
        $category = DocumentCategory::query()->create(['name' => 'Policies', 'slug' => 'policies', 'sort_order' => 1]);
        $document = Document::query()->create(['document_category_id' => $category->id, 'title' => 'Safety policy', 'slug' => 'safety-policy', 'visibility' => 'public', 'controlled' => false, 'approval_status' => 'draft', 'public_version_history' => false]);

        try {
            app(UploadDocumentVersion::class)->handle($actor, $document, UploadedFile::fake()->createWithContent('policy.pdf', '<?php echo "not a pdf";')->mimeType('application/pdf'));
            $this->fail('Spoofed PDF content was accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('document_versions', 0);
        }

        $version = app(UploadDocumentVersion::class)->handle($actor, $document, UploadedFile::fake()->createWithContent('../../board minutes.pdf', "%PDF-1.4\n%%EOF")->mimeType('application/pdf'));
        $this->assertMatchesRegularExpression('#^documents/[a-f0-9-]{36}/v1\.pdf$#', $version->storage_path);
        $this->assertSame('board minutes.pdf', $version->original_filename);
    }
}
