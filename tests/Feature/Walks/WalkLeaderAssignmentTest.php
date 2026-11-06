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
use App\Domain\Walks\Actions\SubmitWalkForPublication;
use App\Domain\Walks\Actions\UpdateWalk;
use App\Domain\Walks\Models\Walk;
use App\Filament\Resources\WalkResource\Pages\CreateWalk;
use App\Filament\Resources\WalkResource\Pages\EditWalk;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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

    public function test_a_name_only_primary_leader_can_be_saved_without_a_registered_account(): void
    {
        $walk = app(SaveWalkDetails::class)->handle(Event::factory()->create(), [
            'primary_leader_name' => 'Alex Walker',
            'primary_leader_id' => null,
        ]);

        $this->assertSame('Alex Walker', $walk->primary_leader_name);
        $this->assertNull($walk->primary_leader_id);
        $this->assertNull($walk->primaryLeader);
    }

    public function test_an_administrator_linking_and_unlinking_a_leader_transfers_walk_management_ownership(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $leader = User::factory()->create(['role' => AccountRole::WalkLeader]);
        $event = Event::factory()->for($administrator, 'organiser')->create();
        $walk = app(SaveWalkDetails::class)->handle($event, [
            'primary_leader_name' => 'Alex Walker',
            'primary_leader_id' => null,
        ]);

        $walk = app(UpdateWalk::class)->handle($walk, $administrator, ['primary_leader_id' => $leader->id]);

        $this->assertSame($leader->id, $event->fresh()->organiser_id);
        $this->assertSame($leader->id, $walk->event->organiser_id);

        $walk = app(UpdateWalk::class)->handle($walk->fresh(), $administrator, [
            'primary_leader_name' => 'Alex Walker',
            'primary_leader_id' => null,
        ]);

        $this->assertSame($administrator->id, $event->fresh()->organiser_id);
        $this->assertSame($administrator->id, $walk->event->organiser_id);
        $this->assertNull($walk->fresh()->primary_leader_id);
    }

    public function test_publication_requires_a_primary_leader_name_or_linked_account(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $event = Event::factory()->for($administrator, 'organiser')->create();
        $walk = app(SaveWalkDetails::class)->handle($event, []);

        try {
            app(SubmitWalkForPublication::class)->handle($walk, $administrator);

            $this->fail('A walk without a named or linked primary leader was published.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('primary_leader', $exception->errors());
        }
    }

    public function test_a_walk_leader_cannot_unlink_their_account_in_favour_of_a_name_only_leader(): void
    {
        $leader = User::factory()->create(['role' => AccountRole::WalkLeader]);
        $event = Event::factory()->for($leader, 'organiser')->create();
        $walk = app(SaveWalkDetails::class)->handle($event, [
            'primary_leader_id' => $leader->id,
        ]);

        try {
            app(UpdateWalk::class)->handle($walk, $leader, [
                'primary_leader_name' => 'Unlinked person',
                'primary_leader_id' => null,
            ]);

            $this->fail('A walk leader was able to replace their linked account with a name-only leader.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('primary_leader_id', $exception->errors());
        }
    }

    public function test_a_walk_leader_cannot_publish_a_name_only_walk(): void
    {
        $leader = User::factory()->create(['role' => AccountRole::WalkLeader]);
        $event = Event::factory()->for($leader, 'organiser')->create();
        $walk = app(SaveWalkDetails::class)->handle($event, [
            'primary_leader_name' => 'Unlinked person',
            'primary_leader_id' => null,
        ]);

        try {
            app(SubmitWalkForPublication::class)->handle($walk, $leader);

            $this->fail('A walk leader was able to publish a name-only walk.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('primary_leader_id', $exception->errors());
        }
    }

    public function test_leader_name_migration_rollback_restores_a_name_only_walk_from_its_event_organiser_before_removing_the_column(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $event = Event::factory()->for($administrator, 'organiser')->create();
        $walk = app(SaveWalkDetails::class)->handle($event, [
            'primary_leader_name' => 'Unlinked person',
            'primary_leader_id' => null,
        ]);
        $migration = require database_path('migrations/2026_11_06_100000_add_primary_leader_name_to_walks_table.php');

        $migration->down();

        $this->assertSame($administrator->id, $walk->fresh()->primary_leader_id);
        $this->assertFalse(Schema::hasColumn('walks', 'primary_leader_name'));

        $migration->up();
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
