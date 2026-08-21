<?php

namespace Tests\Feature\Walks;

use App\Domain\Accounts\Actions\ConfigureRoleCapabilities;
use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Membership\Enums\AccountStatus;
use App\Domain\Walks\Actions\SaveWalkDetails;
use App\Domain\Walks\Actions\UpdateWalk;
use App\Domain\Walks\Models\Walk;
use App\Filament\Resources\WalkResource\Pages\CreateWalk;
use App\Filament\Resources\WalkResource\Pages\EditWalk;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class WalkLeaderAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_eligible_walk_leader_can_be_assigned_as_primary_leader(): void
    {
        $leader = User::factory()->create(['role' => AccountRole::WalkLeader]);

        $walk = app(SaveWalkDetails::class)->handle(Event::factory()->create(), [
            'primary_leader_id' => $leader->id,
        ]);

        $this->assertTrue($walk->primaryLeader->is($leader));
    }

    public function test_eligible_administrator_can_be_assigned_as_primary_leader(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);

        $walk = app(SaveWalkDetails::class)->handle(Event::factory()->create(), [
            'primary_leader_id' => $administrator->id,
        ]);

        $this->assertTrue($walk->primaryLeader->is($administrator));
    }

    public function test_capability_granted_moderator_can_be_assigned_as_a_leader(): void
    {
        $moderator = $this->walkCapableModerator();

        $walk = app(SaveWalkDetails::class)->handle(Event::factory()->create(), [
            'primary_leader_id' => $moderator->id,
        ]);

        $this->assertTrue($walk->primaryLeader->is($moderator));
    }

    public function test_unverified_account_is_rejected_as_primary_leader(): void
    {
        $leader = User::factory()->unverified()->create(['role' => AccountRole::WalkLeader]);

        $this->assertLeaderAssignmentRejected('primary_leader_id', $leader);
    }

    public function test_inactive_account_is_rejected_as_primary_leader(): void
    {
        $leader = User::factory()->create([
            'role' => AccountRole::WalkLeader,
            'account_status' => AccountStatus::Suspended,
        ]);

        $this->assertLeaderAssignmentRejected('primary_leader_id', $leader);
    }

    public function test_account_without_walk_capability_is_rejected_as_primary_leader(): void
    {
        $member = User::factory()->create(['role' => AccountRole::VerifiedMember]);

        $this->assertLeaderAssignmentRejected('primary_leader_id', $member);
    }

    public function test_ineligible_account_is_rejected_as_co_leader(): void
    {
        $primaryLeader = User::factory()->create(['role' => AccountRole::WalkLeader]);
        $member = User::factory()->create(['role' => AccountRole::VerifiedMember]);

        try {
            app(SaveWalkDetails::class)->handle(Event::factory()->create(), [
                'primary_leader_id' => $primaryLeader->id,
                'co_leader_ids' => [$member->id],
            ]);

            $this->fail('An ineligible co-leader was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('co_leader_ids', $exception->errors());
        }
    }

    public function test_walk_with_a_later_ineligible_former_leader_relationship_remains_readable(): void
    {
        $leader = User::factory()->create(['role' => AccountRole::WalkLeader]);
        $event = Event::query()->create([
            'type' => EventType::Walk,
            'title' => 'Historical leader walk',
            'slug' => 'historical-leader-walk',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->subHours(20),
            'status' => EventStatus::Published,
            'is_public' => true,
            'published_at' => now()->subWeek(),
            'organiser_id' => $leader->id,
        ]);
        Walk::query()->create([
            'event_id' => $event->id,
            'primary_leader_id' => $leader->id,
        ]);
        $leader->forceFill(['role' => AccountRole::VerifiedMember])->save();

        $historicalWalk = Walk::query()->with(['event', 'primaryLeader'])->findOrFail($event->walk->id);

        $this->assertSame('Historical leader walk', $historicalWalk->event->title);
        $this->assertTrue($historicalWalk->primaryLeader->is($leader));
    }

    public function test_unrelated_walk_edit_preserves_a_later_ineligible_historical_leader(): void
    {
        $leader = User::factory()->create(['role' => AccountRole::WalkLeader]);
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $event = Event::factory()->for($administrator, 'organiser')->create();
        $walk = app(SaveWalkDetails::class)->handle($event, [
            'primary_leader_id' => $leader->id,
        ]);
        $leader->forceFill(['role' => AccountRole::VerifiedMember])->save();

        app(UpdateWalk::class)->handle($walk, $administrator, ['title' => 'Updated without reassignment']);

        $this->assertSame($leader->id, $walk->fresh()->primary_leader_id);
        $this->assertSame('Updated without reassignment', $event->fresh()->title);
    }

    public function test_admin_form_preserves_later_ineligible_historical_leaders_during_an_unrelated_edit(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $primaryLeader = User::factory()->create(['role' => AccountRole::WalkLeader]);
        $coLeader = User::factory()->create(['role' => AccountRole::WalkLeader]);
        $event = Event::factory()->for($administrator, 'organiser')->create();
        $walk = app(SaveWalkDetails::class)->handle($event, [
            'primary_leader_id' => $primaryLeader->id,
            'co_leader_ids' => [$coLeader->id],
        ]);
        $primaryLeader->forceFill(['role' => AccountRole::VerifiedMember])->save();
        $coLeader->forceFill(['role' => AccountRole::VerifiedMember])->save();

        $this->actingAs($administrator);

        Livewire::test(EditWalk::class, ['record' => $walk->id])
            ->fillForm(['title' => 'Historical leaders retained'])
            ->call('save')
            ->assertHasNoFormErrors();

        $walk->refresh()->load('coLeaders');
        $this->assertSame($primaryLeader->id, $walk->primary_leader_id);
        $this->assertSame([$coLeader->id], $walk->coLeaders->modelKeys());
    }

    public function test_admin_leader_dropdowns_include_only_currently_eligible_accounts(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $leader = User::factory()->create(['role' => AccountRole::WalkLeader]);
        $moderator = $this->walkCapableModerator($administrator);
        $member = User::factory()->create(['role' => AccountRole::VerifiedMember]);
        $unverified = User::factory()->unverified()->create(['role' => AccountRole::WalkLeader]);
        $inactive = User::factory()->create([
            'role' => AccountRole::WalkLeader,
            'account_status' => AccountStatus::Disabled,
        ]);

        $eligibleIds = [$administrator->id, $leader->id, $moderator->id];
        $ineligibleIds = [$member->id, $unverified->id, $inactive->id];
        $hasExpectedOptions = function (Select $select) use ($eligibleIds, $ineligibleIds): bool {
            $options = $select->getOptions();

            return collect($eligibleIds)->every(fn (int $id): bool => array_key_exists($id, $options))
                && collect($ineligibleIds)->every(fn (int $id): bool => ! array_key_exists($id, $options));
        };

        $this->actingAs($administrator);

        Livewire::test(CreateWalk::class)
            ->assertFormFieldExists('primary_leader_id', $hasExpectedOptions)
            ->assertFormFieldExists('co_leader_ids', $hasExpectedOptions);
    }

    private function assertLeaderAssignmentRejected(string $field, User $leader): void
    {
        try {
            app(SaveWalkDetails::class)->handle(Event::factory()->create(), [
                'primary_leader_id' => $leader->id,
            ]);

            $this->fail("An ineligible {$field} was accepted.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    private function walkCapableModerator(?User $administrator = null): User
    {
        $administrator ??= User::factory()->create(['role' => AccountRole::Administrator]);
        $moderator = User::factory()->create(['role' => AccountRole::Moderator]);
        app(ConfigureRoleCapabilities::class)->handle($administrator, AccountRole::Moderator, [
            ModuleCapability::AccessAdministration,
            ModuleCapability::ManageSocials,
            ModuleCapability::ManageOwnWalks,
        ]);

        return $moderator;
    }
}
