<?php

namespace Tests\Feature\Walks;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Accounts\Models\RoleCapability;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Membership\Enums\AccountStatus;
use App\Domain\Walks\Actions\SaveWalkDraft;
use App\Models\User;
use App\Policies\WalkPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class SaveWalkDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_step_one_creates_one_private_actor_owned_draft(): void
    {
        $leader = User::factory()->walkLeader()->create();

        $walk = app(SaveWalkDraft::class)->create($leader, [
            'title' => 'Reservoir circuit',
            'starts_at' => '2026-09-12 09:30:00',
            'meeting_location_name' => 'North gate',
            'primary_leader_id' => User::factory()->walkLeader()->create()->id,
            'is_public' => true,
        ]);

        $this->assertSame(EventStatus::Draft, $walk->event->status);
        $this->assertFalse($walk->event->is_public);
        $this->assertNull($walk->event->published_at);
        $this->assertSame($leader->id, $walk->event->organiser_id);
        $this->assertSame($leader->id, $walk->primary_leader_id);
        $this->assertSame('reservoir-circuit-2026-09-12', $walk->event->slug);
        $this->assertSame('North gate', $walk->meeting_location_name);
        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseCount('walks', 1);
    }

    /** @param array<string, mixed> $attributes */
    #[DataProvider('invalidInitialDetails')]
    public function test_invalid_initial_details_leave_no_partial_event_or_walk(array $attributes): void
    {
        $leader = User::factory()->walkLeader()->create();

        try {
            app(SaveWalkDraft::class)->create($leader, $attributes);
            $this->fail('Invalid initial details were accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('events', 0);
            $this->assertDatabaseCount('walks', 0);
        }
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalidInitialDetails(): array
    {
        return [
            'blank title' => [[
                'title' => '   ',
                'starts_at' => '2026-09-12 09:30:00',
            ]],
            'overlong title' => [[
                'title' => str_repeat('a', 256),
                'starts_at' => '2026-09-12 09:30:00',
            ]],
            'missing start' => [[
                'title' => 'Reservoir circuit',
            ]],
            'invalid start' => [[
                'title' => 'Reservoir circuit',
                'starts_at' => 'not-a-date',
            ]],
        ];
    }

    public function test_create_only_role_cannot_create_a_draft(): void
    {
        RoleCapability::query()
            ->where('role', AccountRole::WalkLeader->value)
            ->whereIn('capability', [
                ModuleCapability::ManageOwnWalks->value,
                ModuleCapability::ManageAllWalks->value,
            ])
            ->delete();
        $leader = User::factory()->walkLeader()->create();

        $this->assertTrue($leader->hasCapability(ModuleCapability::CreateWalks));
        $this->assertFalse(app(WalkPolicy::class)->create($leader));

        $this->expectException(AuthorizationException::class);

        app(SaveWalkDraft::class)->create($leader, $this->validDetails());
    }

    #[DataProvider('inactiveStatuses')]
    public function test_inactive_users_have_no_effective_walk_capability_and_cannot_create_a_draft(AccountStatus $status): void
    {
        $leader = User::factory()->walkLeader()->create(['account_status' => $status]);

        $this->assertFalse($leader->hasCapability(ModuleCapability::CreateWalks));
        $this->assertFalse($leader->hasCapability(ModuleCapability::ManageOwnWalks));
        $this->assertFalse(app(WalkPolicy::class)->create($leader));

        $this->expectException(AuthorizationException::class);

        app(SaveWalkDraft::class)->create($leader, $this->validDetails());
    }

    /** @return array<string, array{AccountStatus}> */
    public static function inactiveStatuses(): array
    {
        return [
            'suspended' => [AccountStatus::Suspended],
            'disabled' => [AccountStatus::Disabled],
        ];
    }

    public function test_unverified_walk_leader_cannot_create_a_draft(): void
    {
        $leader = User::factory()->walkLeader()->unverified()->create();

        $this->assertFalse(app(WalkPolicy::class)->create($leader));

        $this->expectException(AuthorizationException::class);

        app(SaveWalkDraft::class)->create($leader, $this->validDetails());
    }

    public function test_unverified_administrator_cannot_bypass_verification_to_create_a_draft(): void
    {
        $administrator = User::factory()->unverified()->create(['role' => AccountRole::Administrator]);

        $this->assertTrue($administrator->hasCapability(ModuleCapability::ManageAllWalks));
        $this->assertFalse(app(WalkPolicy::class)->create($administrator));

        $this->expectException(AuthorizationException::class);

        app(SaveWalkDraft::class)->create($administrator, $this->validDetails());
    }

    /** @return array<string, mixed> */
    private function validDetails(): array
    {
        return [
            'title' => 'Reservoir circuit',
            'starts_at' => '2026-09-12 09:30:00',
        ];
    }
}
