<?php

namespace Tests\Feature\Governance;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Governance\Models\CommitteeMeeting;
use App\Domain\Governance\Models\CommitteeRole;
use App\Domain\Governance\Queries\PublicCommittee;
use App\Domain\Governance\Queries\PublicCommitteeMeetings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CommitteeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_committee_only_exposes_active_public_fields_in_order(): void
    {
        CommitteeRole::query()->create($this->role(['title' => 'Secretary', 'sort_order' => 20]));
        CommitteeRole::query()->create($this->role(['title' => 'Chair', 'sort_order' => 10, 'private_email' => 'chair-private@example.org', 'private_phone' => '07000000000']));
        CommitteeRole::query()->create($this->role(['title' => 'Former role', 'sort_order' => 1, 'end_date' => now()->subDay()->toDateString()]));
        CommitteeRole::query()->create($this->role(['title' => 'Internal only', 'sort_order' => 5, 'publicly_visible' => false]));

        $this->assertSame(['Chair', 'Secretary'], app(PublicCommittee::class)->get()->pluck('title')->all());
        $this->get('/committee')->assertOk()->assertSeeText('Chair')->assertDontSee('chair-private@example.org')->assertDontSee('07000000000')->assertDontSeeText('Internal only');
    }

    public function test_meeting_visibility_keeps_internal_notes_private(): void
    {
        CommitteeMeeting::query()->create(['title' => 'Open committee meeting', 'meeting_date' => now()->subMonth()->toDateString(), 'agenda' => 'Regular business', 'attendee_metadata' => ['attendees' => ['Chair']], 'internal_notes' => 'Confidential discussion', 'visibility' => 'public']);
        CommitteeMeeting::query()->create(['title' => 'Private committee meeting', 'meeting_date' => now()->toDateString(), 'internal_notes' => 'Private', 'visibility' => 'committee']);

        $this->assertSame(['Open committee meeting'], app(PublicCommitteeMeetings::class)->get()->pluck('title')->all());
        $this->get('/committee/meetings')->assertOk()->assertSeeText('Open committee meeting')->assertDontSeeText('Confidential discussion')->assertDontSeeText('Private committee meeting');
    }

    public function test_committee_and_meeting_admin_are_governance_manager_only(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator, 'email_verified_at' => now()]);
        $member = User::factory()->create(['role' => AccountRole::RegisteredUser, 'email_verified_at' => now()]);
        foreach (['/admin/committee-roles', '/admin/committee-meetings'] as $path) {
            $this->actingAs($administrator)->get($path)->assertOk();
            $this->actingAs($member)->get($path)->assertForbidden();
        }
    }

    /** @param array<string, mixed> $overrides */
    private function role(array $overrides = []): array
    {
        return array_merge(['title' => 'Committee member', 'public_name' => 'Alex M.', 'sort_order' => 10, 'start_date' => now()->subYear()->toDateString(), 'active' => true, 'publicly_visible' => true, 'public_details' => 'Volunteer committee member.'], $overrides);
    }
}
