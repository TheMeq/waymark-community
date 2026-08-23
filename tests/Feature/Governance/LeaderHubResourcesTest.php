<?php

namespace Tests\Feature\Governance;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Governance\Models\Document;
use App\Domain\Governance\Models\DocumentCategory;
use App\Domain\Governance\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LeaderHubResourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_leader_hub_lists_current_leader_guidance_and_forms_without_exposing_other_restricted_documents(): void
    {
        Storage::fake('local');
        $leader = User::factory()->create(['role' => AccountRole::WalkLeader, 'email_verified_at' => now()]);
        $guidance = $this->document($leader, 'Leader guidance', 'leader-guidance', 'leader');
        $committee = $this->document($leader, 'Committee budget', 'committee-budget', 'committee');

        $this->actingAs($leader)->get('/leader-hub')
            ->assertOk()
            ->assertSeeText('Leader resources')
            ->assertSeeText($guidance->title)
            ->assertSee(route('leader-hub.documents.download', $guidance), false)
            ->assertDontSeeText($committee->title);
    }

    public function test_current_leader_document_download_is_private_and_authorised(): void
    {
        Storage::fake('local');
        $leader = User::factory()->create(['role' => AccountRole::WalkLeader, 'email_verified_at' => now()]);
        $member = User::factory()->create(['role' => AccountRole::RegisteredUser, 'email_verified_at' => now()]);
        $document = $this->document($leader, 'Walk risk form', 'walk-risk-form', 'leader');
        Storage::disk('local')->put($document->currentVersion->storage_path, 'private form');

        $this->get(route('leader-hub.documents.download', $document))->assertRedirect('/login');
        $this->actingAs($member)->get(route('leader-hub.documents.download', $document))->assertForbidden();
        $this->actingAs($leader)->get(route('leader-hub.documents.download', $document))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=walk-risk-form.pdf');
    }

    private function document(User $creator, string $title, string $slug, string $visibility): Document
    {
        $category = DocumentCategory::query()->firstOrCreate(['slug' => 'leader-resources'], ['name' => 'Leader resources', 'sort_order' => 1]);
        $document = Document::query()->create([
            'document_category_id' => $category->id,
            'title' => $title,
            'slug' => $slug,
            'description' => 'Current guidance for walk leaders.',
            'visibility' => $visibility,
            'publication_date' => now()->toDateString(),
            'public_version_history' => false,
            'download_count' => 0,
            'controlled' => false,
            'approval_status' => 'approved',
        ]);
        $version = DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'storage_disk' => 'local',
            'storage_path' => 'documents/'.Str::uuid().'/v1.pdf',
            'original_filename' => $slug.'.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 12,
            'created_by_user_id' => $creator->id,
            'published_at' => now(),
        ]);
        $document->forceFill(['current_version_id' => $version->id])->save();

        return $document->refresh()->load('currentVersion');
    }
}
