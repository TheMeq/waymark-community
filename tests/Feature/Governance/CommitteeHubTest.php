<?php

namespace Tests\Feature\Governance;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Accounts\Models\RoleCapability;
use App\Domain\Governance\Models\CommitteeHubLink;
use App\Domain\Governance\Models\CommitteeMeeting;
use App\Domain\Governance\Models\CommitteeRole;
use App\Domain\Governance\Models\Document;
use App\Domain\Governance\Models\DocumentCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CommitteeHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_committee_hub_is_restricted_by_dedicated_capability(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $moderator = User::factory()->create(['role' => AccountRole::Moderator, 'email_verified_at' => now()]);

        $this->actingAs($administrator)->get('/committee-hub')->assertOk();
        $this->actingAs($moderator)->get('/committee-hub')->assertForbidden();

        RoleCapability::query()->create(['role' => AccountRole::Moderator, 'capability' => ModuleCapability::AccessCommitteeHub]);
        $this->actingAs($moderator->fresh())->get('/committee-hub')->assertOk();
    }

    public function test_hub_aggregates_private_contacts_documents_meetings_and_curated_links(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        CommitteeRole::query()->create(['title' => 'Chair', 'public_name' => 'Alex M.', 'sort_order' => 10, 'active' => true, 'publicly_visible' => true, 'private_email' => 'chair@example.org', 'private_notes' => 'Preferred contact after 6pm.']);
        CommitteeMeeting::query()->create(['title' => 'Planning meeting', 'meeting_date' => now()->toDateString(), 'internal_notes' => 'Discuss succession planning.', 'visibility' => 'committee']);
        $category = DocumentCategory::query()->create(['name' => 'Committee', 'slug' => 'committee', 'sort_order' => 10]);
        Document::query()->create(['document_category_id' => $category->id, 'title' => 'Committee handbook', 'slug' => 'committee-handbook', 'visibility' => 'committee', 'public_version_history' => false, 'download_count' => 0, 'controlled' => false, 'approval_status' => 'approved']);
        CommitteeHubLink::query()->create(['label' => 'Shared guidance', 'url' => 'https://example.org/guidance', 'notes' => 'Reference only', 'sort_order' => 10, 'active' => true]);

        $this->actingAs($administrator)->get('/committee-hub')->assertOk()
            ->assertSeeText('chair@example.org')->assertSeeText('Preferred contact after 6pm.')
            ->assertSeeText('Planning meeting')->assertSeeText('Discuss succession planning.')
            ->assertSeeText('Committee handbook')->assertSeeText('Shared guidance');
    }
}
