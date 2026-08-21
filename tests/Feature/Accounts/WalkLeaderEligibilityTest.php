<?php

namespace Tests\Feature\Accounts;

use App\Domain\Accounts\Actions\ConfigureRoleCapabilities;
use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Membership\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WalkLeaderEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_walk_leader_is_eligible_to_lead_walks(): void
    {
        $leader = User::factory()->create(['role' => AccountRole::WalkLeader]);

        $this->assertTrue($leader->isEligibleWalkLeader());
    }

    public function test_administrator_with_walk_capability_is_eligible_to_lead_walks(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);

        $this->assertTrue($administrator->isEligibleWalkLeader());
    }

    public function test_moderator_granted_walk_capability_is_eligible_to_lead_walks(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $moderator = User::factory()->create(['role' => AccountRole::Moderator]);
        app(ConfigureRoleCapabilities::class)->handle($administrator, AccountRole::Moderator, [
            ModuleCapability::AccessAdministration,
            ModuleCapability::ManageSocials,
            ModuleCapability::ManageOwnWalks,
        ]);

        $this->assertTrue($moderator->isEligibleWalkLeader());
    }

    public function test_active_verified_account_without_walk_capability_is_not_eligible_to_lead_walks(): void
    {
        $member = User::factory()->create(['role' => AccountRole::VerifiedMember]);

        $this->assertFalse($member->isEligibleWalkLeader());
    }

    public function test_unverified_account_with_walk_capability_is_not_eligible_to_lead_walks(): void
    {
        $leader = User::factory()->unverified()->create(['role' => AccountRole::WalkLeader]);

        $this->assertFalse($leader->isEligibleWalkLeader());
    }

    public function test_inactive_account_with_walk_capability_is_not_eligible_to_lead_walks(): void
    {
        $administrator = User::factory()->create([
            'role' => AccountRole::Administrator,
            'account_status' => AccountStatus::Suspended,
        ]);

        $this->assertFalse($administrator->isEligibleWalkLeader());
    }

    public function test_public_leader_profile_is_available_to_any_eligible_account_role(): void
    {
        $administrator = User::factory()->create([
            'role' => AccountRole::Administrator,
            'display_name' => 'Alex A.',
            'public_profile_enabled' => true,
            'public_profile_slug' => 'alex-admin',
            'public_profile_introduction' => 'An administrator who also leads walks.',
        ]);

        $this->get('/leaders/alex-admin')
            ->assertOk()
            ->assertSee('Alex A.')
            ->assertSee('An administrator who also leads walks.');
    }
}
