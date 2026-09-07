<?php

namespace Tests\Feature\Walks;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Accounts\Models\RoleCapability;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Membership\Enums\AccountStatus;
use App\Domain\Walks\Actions\SaveWalkDraft;
use App\Domain\Walks\Models\Grade;
use App\Domain\Walks\Models\Tag;
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

    public function test_updates_merge_into_the_same_draft_without_erasing_omitted_relationships(): void
    {
        $leader = User::factory()->walkLeader()->create();
        $coLeader = User::factory()->walkLeader()->create();
        $grade = Grade::query()->create([
            'display_order' => 1,
            'name' => 'Moderate',
            'description' => 'Steady terrain',
        ]);
        $tag = Tag::query()->create(['name' => 'Riverside']);
        $draft = app(SaveWalkDraft::class)->create($leader, $this->validDetails());

        $updated = app(SaveWalkDraft::class)->update($draft, $leader, [
            'grade_id' => $grade->id,
            'tag_ids' => [$tag->id],
            'co_leader_ids' => [$coLeader->id],
            'distance' => 8.5,
        ]);
        $updated = app(SaveWalkDraft::class)->update($updated, $leader, [
            'directions' => 'Meet beside the northern entrance.',
            'is_public_transport_friendly' => true,
            'public_transport_station_stop' => 'Reservoir Road',
        ]);

        $this->assertSame($draft->id, $updated->id);
        $this->assertSame($draft->event_id, $updated->event_id);
        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseCount('walks', 1);
        $this->assertSame($grade->id, $updated->grade_id);
        $this->assertSame('8.50', $updated->distance);
        $this->assertSame('Meet beside the northern entrance.', $updated->directions);
        $this->assertSame([$tag->id], $updated->tags->modelKeys());
        $this->assertSame([$coLeader->id], $updated->coLeaders->modelKeys());
    }

    public function test_updating_title_and_start_date_preserves_the_draft_slug(): void
    {
        $leader = User::factory()->walkLeader()->create();
        $draft = app(SaveWalkDraft::class)->create($leader, $this->validDetails());

        $updated = app(SaveWalkDraft::class)->update($draft, $leader, [
            'title' => 'Lakeside circuit',
            'starts_at' => '2026-10-03 10:00:00',
        ]);

        $this->assertSame('Lakeside circuit', $updated->event->title);
        $this->assertSame('2026-10-03', $updated->event->starts_at->format('Y-m-d'));
        $this->assertSame('reservoir-circuit-2026-09-12', $updated->event->slug);
    }

    public function test_another_organiser_cannot_update_the_draft(): void
    {
        $leader = User::factory()->walkLeader()->create();
        $otherLeader = User::factory()->walkLeader()->create();
        $draft = app(SaveWalkDraft::class)->create($leader, $this->validDetails());

        try {
            app(SaveWalkDraft::class)->update($draft, $otherLeader, ['distance' => 8.5]);
            $this->fail('Another organiser updated the draft.');
        } catch (AuthorizationException) {
            $this->assertNull($draft->fresh()->distance);
            $this->assertSame('Reservoir circuit', $draft->event->fresh()->title);
        }
    }

    #[DataProvider('nonDraftStatuses')]
    public function test_non_draft_walk_cannot_be_updated_from_the_wizard(EventStatus $status): void
    {
        $leader = User::factory()->walkLeader()->create();
        $walk = app(SaveWalkDraft::class)->create($leader, $this->validDetails());
        $walk->event->forceFill(['status' => $status])->save();

        try {
            app(SaveWalkDraft::class)->update($walk, $leader, ['title' => 'Changed title']);
            $this->fail('A non-draft walk was updated from the wizard.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('draft', $exception->errors());
            $this->assertSame('Reservoir circuit', $walk->event->fresh()->title);
            $this->assertSame($status, $walk->event->fresh()->status);
        }
    }

    /** @return array<string, array{EventStatus}> */
    public static function nonDraftStatuses(): array
    {
        return [
            'pending approval' => [EventStatus::PendingApproval],
            'published' => [EventStatus::Published],
        ];
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
