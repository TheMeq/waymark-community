<?php

namespace Tests\Feature\Governance;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Governance\Actions\ApproveControlledDocument;
use App\Domain\Governance\Actions\PublishDocumentVersion;
use App\Domain\Governance\Models\Document;
use App\Domain\Governance\Models\DocumentCategory;
use App\Domain\Governance\Models\DocumentVersion;
use App\Domain\Governance\Queries\DocumentsDueForReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_review_query_returns_approaching_and_overdue_controlled_documents(): void
    {
        $overdue = $this->document(['slug' => 'overdue', 'controlled' => true, 'approval_status' => 'approved', 'review_date' => now()->subDay()->toDateString()]);
        $approaching = $this->document(['slug' => 'approaching', 'controlled' => true, 'approval_status' => 'approved', 'review_date' => now()->addDays(14)->toDateString()]);
        $this->document(['slug' => 'later', 'controlled' => true, 'approval_status' => 'approved', 'review_date' => now()->addMonths(6)->toDateString()]);

        $this->assertSame([$overdue->id, $approaching->id], app(DocumentsDueForReview::class)->get(30)->pluck('id')->all());
    }

    public function test_document_administration_is_governance_manager_only(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $member = User::factory()->create(['role' => AccountRole::RegisteredUser, 'email_verified_at' => now()]);
        foreach (['/admin/documents', '/admin/document-categories'] as $path) {
            $this->actingAs($administrator)->get($path)->assertOk();
            $this->actingAs($member)->get($path)->assertForbidden();
        }
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
}
