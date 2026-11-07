<?php

namespace Tests\Feature\Walks;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Walks\Actions\SaveWalkDraft;
use App\Domain\Walks\Models\Walk;
use App\Domain\Walks\Models\WalkFieldSettings;
use App\Filament\Resources\WalkResource\Pages\ListWalks;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class WalkListActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_sort_uses_latest_start_first_and_can_be_changed_to_ascending(): void
    {
        $admin = User::factory()->create(['role' => AccountRole::Administrator]);
        $latest = $this->draft($admin, 'Latest walk', '2027-02-01 10:00');
        $earliest = $this->draft($admin, 'Earliest walk', '2025-02-01 10:00');
        $middle = $this->draft($admin, 'Middle walk', '2026-02-01 10:00');
        Livewire::actingAs($admin)->test(ListWalks::class)
            ->assertCanSeeTableRecords([$latest, $middle, $earliest], inOrder: true)
            ->sortTable('event.starts_at', 'asc')
            ->assertCanSeeTableRecords([$earliest, $middle, $latest], inOrder: true);
    }

    public function test_administrator_can_publish_a_draft_from_its_table_row(): void
    {
        $admin = User::factory()->create(['role' => AccountRole::Administrator]);
        $walk = $this->draft($admin, 'Ready walk', '2026-12-01 10:00');
        Livewire::actingAs($admin)->test(ListWalks::class)
            ->assertTableActionHasLabel('edit', 'Continue draft', $walk)
            ->assertTableActionVisible('publish', $walk)
            ->assertTableActionHasLabel('publish', 'Publish', $walk)
            ->callTableAction('publish', $walk)->assertHasNoTableActionErrors()
            ->assertNotified('Walk published')
            ->assertTableActionHidden('publish', $walk);
        $this->assertSame(EventStatus::Published, $walk->event->fresh()->status);
        $this->assertTrue($walk->event->fresh()->is_public);
    }

    public function test_leader_submission_from_the_table_retains_the_approval_requirement(): void
    {
        $leader = User::factory()->walkLeader()->create();
        $walk = $this->draft($leader, 'Leader walk', '2026-12-01 10:00');
        Livewire::actingAs($leader)->test(ListWalks::class)
            ->assertTableActionHasLabel('publish', 'Submit for approval', $walk)
            ->callTableAction('publish', $walk)->assertHasNoTableActionErrors()
            ->assertNotified('Walk submitted for approval');
        $this->assertSame(EventStatus::PendingApproval, $walk->event->fresh()->status);
        $this->assertFalse($walk->event->fresh()->is_public);
    }

    public function test_an_administrator_can_publish_a_named_unlinked_leader_from_the_table(): void
    {
        $admin = User::factory()->create(['role' => AccountRole::Administrator]);
        $walk = $this->draft($admin, 'Archive walk', '2023-12-01 10:00');
        $walk->update(['primary_leader_id' => null, 'primary_leader_name' => 'Guest Leader']);
        Livewire::actingAs($admin)->test(ListWalks::class)
            ->callTableAction('publish', $walk)->assertHasNoTableActionErrors();
        $this->assertTrue($walk->event->fresh()->is_public);
    }

    public function test_a_leader_can_publish_directly_from_the_table_when_the_setting_allows_it(): void
    {
        WalkFieldSettings::query()->create(['leaders_can_publish_directly' => true]);
        $leader = User::factory()->walkLeader()->create();
        $walk = $this->draft($leader, 'Direct publication', '2026-12-01 10:00');
        Livewire::actingAs($leader)->test(ListWalks::class)
            ->assertTableActionHasLabel('publish', 'Publish', $walk)
            ->callTableAction('publish', $walk)->assertHasNoTableActionErrors()
            ->assertNotified('Walk published');
        $this->assertTrue($walk->event->fresh()->is_public);
    }

    public function test_row_publication_cannot_bypass_the_missing_leader_check(): void
    {
        $admin = User::factory()->create(['role' => AccountRole::Administrator]);
        $walk = $this->draft($admin, 'Incomplete walk', '2026-12-01 10:00');
        $walk->update(['primary_leader_id' => null, 'primary_leader_name' => null]);
        Livewire::actingAs($admin)->test(ListWalks::class)
            ->callTableAction('publish', $walk)->assertHasNoTableActionErrors()
            ->assertNotified('Complete the walk before publishing');
        $this->assertSame(EventStatus::Draft, $walk->event->fresh()->status);
        $this->assertFalse($walk->event->fresh()->is_public);
    }

    private function draft(User $owner, string $title, string $startsAt): Walk
    {
        return app(SaveWalkDraft::class)->create($owner, ['title' => $title, 'starts_at' => $startsAt]);
    }
}
