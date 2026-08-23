<?php

namespace Tests\Feature\Governance;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Governance\Actions\ApproveControlledDocument;
use App\Domain\Governance\Actions\PublishDocumentVersion;
use App\Domain\Governance\Actions\SendDocumentReviewReminders;
use App\Domain\Governance\Actions\UploadDocumentVersion;
use App\Domain\Governance\Mail\DocumentReviewReminderMail;
use App\Domain\Governance\Models\Document;
use App\Domain\Governance\Models\DocumentCategory;
use App\Domain\Governance\Models\DocumentVersion;
use App\Domain\Governance\Queries\DocumentsDueForReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class DocumentLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_versions_preserve_history_and_only_one_is_current(): void
    {
        $actor = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $document = $this->document();
        $version1 = $this->version($document, $actor, 1);
        $version2 = $this->version($document, $actor, 2);

        app(PublishDocumentVersion::class)->handle($actor, $document, $version1);
        app(PublishDocumentVersion::class)->handle($actor, $document, $version2);

        $this->assertSame($version2->id, $document->fresh()->current_version_id);
        $this->assertSame([1, 2], $document->versions()->orderBy('version_number')->pluck('version_number')->all());
        $this->assertNotNull($version1->fresh()->published_at);
    }

    public function test_governance_manager_can_upload_a_safe_private_version_and_member_cannot(): void
    {
        Storage::fake('local');
        $administrator = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $member = User::factory()->create(['role' => AccountRole::RegisteredUser, 'email_verified_at' => now()]);
        $document = $this->document();

        $version = app(UploadDocumentVersion::class)->handle($administrator, $document, UploadedFile::fake()->createWithContent('walking-policy.pdf', "%PDF-1.4\n%%EOF")->mimeType('application/pdf'));

        $this->assertSame(1, $version->version_number);
        $this->assertMatchesRegularExpression('#^documents/[a-f0-9-]{36}/v1\.pdf$#', $version->storage_path);
        Storage::disk('local')->assertExists($version->storage_path);

        $this->expectException(ValidationException::class);
        app(UploadDocumentVersion::class)->handle($member, $document, UploadedFile::fake()->create('unsafe.pdf', 12, 'application/pdf'));
    }

    public function test_controlled_document_requires_approval_and_records_approver(): void
    {
        $actor = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $document = $this->document(['controlled' => true, 'approval_status' => 'draft', 'review_date' => now()->addMonth()->toDateString()]);
        $version = $this->version($document, $actor, 1);

        try {
            app(PublishDocumentVersion::class)->handle($actor, $document, $version);
            $this->fail('A controlled draft was published without approval.');
        } catch (ValidationException) {
            $this->assertNull($document->fresh()->current_version_id);
        }

        app(ApproveControlledDocument::class)->handle($actor, $document);
        app(PublishDocumentVersion::class)->handle($actor, $document, $version);

        $this->assertSame('approved', $document->fresh()->approval_status);
        $this->assertSame($actor->id, $document->fresh()->approver_id);
    }

    public function test_public_download_increments_only_aggregate_count_and_history_visibility_is_configurable(): void
    {
        Storage::fake('local');
        $actor = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $document = $this->document(['public_version_history' => true]);
        $version = $this->version($document, $actor, 1);
        Storage::disk('local')->put($version->storage_path, 'policy bytes');
        app(PublishDocumentVersion::class)->handle($actor, $document, $version);

        $this->get('/documents/'.$document->slug)->assertOk()->assertSeeText('Version 1');
        $this->get(route('documents.download', [$document->slug, $version]))->assertOk();

        $this->assertSame(1, $document->fresh()->download_count);
        $this->assertFalse(Schema::hasTable('document_downloads'));
    }

    public function test_list_detail_and_download_share_the_current_document_availability_rules(): void
    {
        Storage::fake('local');
        $actor = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $available = $this->publishedDocument($actor, ['title' => 'Available policy', 'slug' => 'available-policy']);
        $future = $this->publishedDocument($actor, ['title' => 'Future policy', 'slug' => 'future-policy', 'publication_date' => today()->addDay()]);
        $controlledDraft = $this->publishedDocument($actor, ['title' => 'Unapproved controlled policy', 'slug' => 'unapproved-controlled-policy']);
        $controlledDraft->update(['controlled' => true, 'approval_status' => 'draft']);
        $versionNotDue = $this->publishedDocument($actor, ['title' => 'Version not due', 'slug' => 'version-not-due']);
        $versionNotDue->currentVersion->update(['published_at' => now()->addHour()]);

        $this->get(route('documents.index'))->assertOk()->assertSeeText($available->title)
            ->assertDontSeeText($future->title)->assertDontSeeText($controlledDraft->title)->assertDontSeeText($versionNotDue->title);
        foreach ([$future, $controlledDraft, $versionNotDue] as $unavailable) {
            $this->get(route('documents.show', $unavailable->slug))->assertNotFound();
            $this->get(route('documents.download', [$unavailable->slug, $unavailable->currentVersion]))->assertNotFound();
        }
        $this->assertSame(0, $future->fresh()->download_count);
        $this->assertSame(0, $controlledDraft->fresh()->download_count);
        $this->assertSame(0, $versionNotDue->fresh()->download_count);
    }

    public function test_document_audiences_are_enforced_for_list_detail_and_direct_download_routes(): void
    {
        Storage::fake('local');
        $administrator = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $registered = User::factory()->create(['role' => AccountRole::RegisteredUser, 'email_verified_at' => now()]);
        $leader = User::factory()->create(['role' => AccountRole::WalkLeader, 'email_verified_at' => now()]);
        $committee = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $registeredDocument = $this->publishedDocument($administrator, ['title' => 'Member handbook', 'slug' => 'member-handbook', 'visibility' => 'registered']);
        $leaderDocument = $this->publishedDocument($administrator, ['title' => 'Leader handbook', 'slug' => 'leader-handbook', 'visibility' => 'leader']);
        $committeeDocument = $this->publishedDocument($administrator, ['title' => 'Committee handbook', 'slug' => 'committee-handbook', 'visibility' => 'committee']);

        $this->get(route('documents.show', $registeredDocument->slug))->assertNotFound();
        $this->actingAs($registered)->get(route('documents.index'))->assertSeeText('Member handbook')->assertDontSeeText('Leader handbook')->assertDontSeeText('Committee handbook');
        $this->actingAs($leader)->get(route('documents.download', [$leaderDocument->slug, $leaderDocument->currentVersion]))->assertOk();
        $this->actingAs($registered)->get(route('documents.download', [$leaderDocument->slug, $leaderDocument->currentVersion]))->assertNotFound();
        $this->actingAs($committee)->get(route('documents.download', [$committeeDocument->slug, $committeeDocument->currentVersion]))->assertOk();
        $this->assertSame(1, $leaderDocument->fresh()->download_count);
        $this->assertSame(1, $committeeDocument->fresh()->download_count);
    }

    public function test_restricted_documents_never_expose_version_history_and_leader_download_uses_the_same_boundary(): void
    {
        Storage::fake('local');
        $administrator = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $leader = User::factory()->create(['role' => AccountRole::WalkLeader, 'email_verified_at' => now()]);
        $document = $this->publishedDocument($administrator, ['visibility' => 'leader', 'public_version_history' => true]);
        $old = $this->version($document, $administrator, 2);
        Storage::disk('local')->put($old->storage_path, 'old');
        $old->update(['published_at' => now()->subDay()]);

        $this->actingAs($leader)->get(route('documents.show', $document->slug))->assertOk()->assertDontSeeText('Version history');
        $this->actingAs($leader)->get(route('documents.download', [$document->slug, $old]))->assertNotFound();
        $this->actingAs($leader)->get(route('leader-hub.documents.download', $document))->assertOk();

        $document->update(['publication_date' => today()->addDay()]);
        $this->actingAs($leader)->get(route('leader-hub.documents.download', $document))->assertNotFound();
        $this->assertSame(1, $document->fresh()->download_count);
    }

    public function test_review_query_returns_approaching_and_overdue_controlled_documents(): void
    {
        $overdue = $this->document(['slug' => 'overdue', 'controlled' => true, 'approval_status' => 'approved', 'review_date' => now()->subDay()->toDateString()]);
        $approaching = $this->document(['slug' => 'approaching', 'controlled' => true, 'approval_status' => 'approved', 'review_date' => now()->addDays(14)->toDateString()]);
        $this->document(['slug' => 'later', 'controlled' => true, 'approval_status' => 'approved', 'review_date' => now()->addMonths(6)->toDateString()]);

        $this->assertSame([$overdue->id, $approaching->id], app(DocumentsDueForReview::class)->get(30)->pluck('id')->all());
    }

    public function test_selected_document_review_reminders_send_once_per_day_to_administrators(): void
    {
        Mail::fake();
        $administrator = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $category = DocumentCategory::query()->create(['name' => 'Policies', 'slug' => 'policies', 'sort_order' => 10, 'review_reminders_enabled' => true]);
        $document = Document::query()->create([
            'document_category_id' => $category->id,
            'title' => 'Safety policy',
            'slug' => 'safety-policy',
            'visibility' => 'leader',
            'public_version_history' => false,
            'download_count' => 0,
            'controlled' => true,
            'approval_status' => 'approved',
            'review_date' => now()->addDays(7)->toDateString(),
            'review_email_reminder' => true,
        ]);

        $this->assertSame(1, app(SendDocumentReviewReminders::class)->handle());
        $this->assertSame(0, app(SendDocumentReviewReminders::class)->handle());
        Mail::assertSent(DocumentReviewReminderMail::class, fn (DocumentReviewReminderMail $mail): bool => $mail->hasTo($administrator->email) && $mail->document->is($document));
    }

    public function test_document_administration_is_governance_manager_only(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $member = User::factory()->create(['role' => AccountRole::RegisteredUser, 'email_verified_at' => now()]);
        $document = $this->document();
        foreach (['/admin/documents', '/admin/document-categories'] as $path) {
            $this->actingAs($administrator)->get($path)->assertOk();
            $this->actingAs($member)->get($path)->assertForbidden();
        }
        $this->actingAs($administrator)->get('/admin/documents/'.$document->id.'/edit')->assertOk()->assertSeeText('Upload version');
    }

    /** @param array<string, mixed> $overrides */
    private function document(array $overrides = []): Document
    {
        $category = DocumentCategory::query()->firstOrCreate(['name' => 'Policies'], ['slug' => 'policies', 'sort_order' => 10]);

        return Document::query()->create(array_merge(['document_category_id' => $category->id, 'title' => 'Walking policy', 'slug' => 'walking-policy', 'description' => 'Current guidance.', 'visibility' => 'public', 'publication_date' => now()->toDateString(), 'public_version_history' => false, 'download_count' => 0, 'controlled' => false, 'approval_status' => 'approved'], $overrides));
    }

    private function version(Document $document, User $actor, int $number): DocumentVersion
    {
        return $document->versions()->create(['version_number' => $number, 'storage_disk' => 'local', 'storage_path' => "documents/3f2504e0-4f89-41d3-9a0c-0305e82c3300/v{$number}.pdf", 'original_filename' => "walking-policy-v{$number}.pdf", 'mime_type' => 'application/pdf', 'file_size_bytes' => 12, 'created_by_user_id' => $actor->id]);
    }

    /** @param array<string, mixed> $overrides */
    private function publishedDocument(User $actor, array $overrides = []): Document
    {
        $document = $this->document($overrides);
        $version = $this->version($document, $actor, 1);
        Storage::disk('local')->put($version->storage_path, 'document');
        app(PublishDocumentVersion::class)->handle($actor, $document, $version);

        return $document->refresh()->load('currentVersion');
    }
}
