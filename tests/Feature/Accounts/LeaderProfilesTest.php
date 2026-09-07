<?php

namespace Tests\Feature\Accounts;

use App\Domain\Accounts\Actions\ConfigureRoleCapabilities;
use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Walks\Actions\SaveWalkDetails;
use App\Domain\Walks\Enums\DuplicateWalkCopyGroup;
use App\Domain\Walks\Models\Walk;
use App\Filament\Resources\WalkResource;
use App\Models\User;
use App\ViewModels\PublicWalkDetailViewModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LeaderProfilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_leader_profile_preferences_are_persisted_on_the_user_account(): void
    {
        $this->assertTrue(Schema::hasColumns('users', [
            'public_profile_enabled',
            'public_profile_slug',
            'public_profile_introduction',
        ]));
    }

    public function test_opted_in_eligible_leader_profile_shows_only_safe_public_details_and_upcoming_walks(): void
    {
        $leader = $this->leader([
            'profile_photo_reference' => '/images/demo/lakeside-friends.png',
        ]);
        $this->walkEvent('Primary leader walk', $leader, $leader);
        $coLed = $this->walkEvent('Co-led walk', $leader, User::factory()->walkLeader()->create(), [$leader]);
        $this->walkEvent('Draft walk', $leader, $leader, [], [
            'status' => EventStatus::Draft,
            'is_public' => false,
            'published_at' => null,
        ]);
        $this->walkEvent('Past walk', $leader, $leader, [], [
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);

        $response = $this->get('/leaders/morgan-m');

        $response->assertOk()
            ->assertSee('Morgan M.')
            ->assertSee('A friendly local walk leader.')
            ->assertSee('Primary leader walk')
            ->assertSee($coLed->title)
            ->assertDontSee('Draft walk')
            ->assertDontSee('Past walk')
            ->assertDontSee($leader->name)
            ->assertDontSee($leader->email)
            ->assertDontSee('07000 111222')
            ->assertSee('data-leader-profile-photo', false);

        $this->assertSame(1, substr_count($response->getContent(), 'Co-led walk'));
    }

    public function test_public_profiles_return_not_found_when_opt_in_or_leader_eligibility_is_missing(): void
    {
        $notOptedIn = $this->leader([
            'public_profile_enabled' => false,
            'public_profile_slug' => 'not-opted-in',
        ]);
        $unverified = $this->leader([
            'public_profile_slug' => 'unverified-leader',
            'email_verified_at' => null,
        ]);
        $notALeader = $this->leader([
            'public_profile_slug' => 'not-a-leader',
            'role' => AccountRole::RegisteredUser,
        ]);

        foreach ([$notOptedIn, $unverified, $notALeader] as $user) {
            $this->get('/leaders/'.$user->public_profile_slug)->assertNotFound();
        }
    }

    public function test_public_walk_cards_link_only_to_eligible_opted_in_leader_profiles(): void
    {
        $leader = $this->leader();
        $this->walkEvent('Linked leader walk', $leader, $leader);

        $this->get('/walks')
            ->assertOk()
            ->assertSee(route('leaders.show', 'morgan-m'), false)
            ->assertDontSee($leader->name);
    }

    public function test_public_walk_detail_links_only_to_an_eligible_opted_in_leader_profile(): void
    {
        $leader = $this->leader();
        $event = $this->walkEvent('Linked leader walk', $leader, $leader);

        $details = PublicWalkDetailViewModel::fromEvent(
            $event->fresh(['walk.grade', 'walk.primaryLeader', 'walk.coLeaders', 'walk.tags', 'updates']),
            new SiteProfile,
        );

        $this->assertStringContainsString(route('leaders.show', 'morgan-m'), $details['leader_attribution']);
        $this->assertStringNotContainsString($leader->name, $details['leader_attribution']);
    }

    public function test_public_profile_and_attribution_disappear_when_the_walk_leader_role_loses_manage_own_walks(): void
    {
        $leader = $this->leader();
        $event = $this->walkEvent('Revoked profile walk', $leader, $leader);

        $this->get('/leaders/morgan-m')->assertOk();

        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        app(ConfigureRoleCapabilities::class)->handle($administrator, AccountRole::WalkLeader, [
            ModuleCapability::AccessAdministration,
            ModuleCapability::CreateWalks,
            ModuleCapability::ManageOwnEventUpdates,
        ]);

        $this->get('/leaders/morgan-m')->assertNotFound();
        $this->get('/walks')->assertDontSee(route('leaders.show', 'morgan-m'), false);

        $details = PublicWalkDetailViewModel::fromEvent(
            $event->fresh(['walk.grade', 'walk.primaryLeader', 'walk.coLeaders', 'walk.tags', 'updates']),
            new SiteProfile,
        );

        $this->assertStringNotContainsString(route('leaders.show', 'morgan-m'), $details['leader_attribution']);
    }

    public function test_invalid_or_absent_profile_photo_references_are_omitted_without_exposing_the_reference(): void
    {
        $leader = $this->leader([
            'profile_photo_reference' => '/private/member-photo.png',
        ]);

        $this->get('/leaders/morgan-m')
            ->assertOk()
            ->assertDontSee('data-leader-profile-photo', false)
            ->assertDontSee('/private/member-photo.png', false);
    }

    public function test_non_opted_in_leader_attribution_does_not_expose_a_profile_address(): void
    {
        $leader = $this->leader([
            'public_profile_enabled' => false,
            'public_profile_slug' => 'private-leader',
        ]);
        $this->walkEvent('Private attribution walk', $leader, $leader);

        $this->get('/walks')
            ->assertOk()
            ->assertDontSee('/leaders/private-leader', false);
    }

    public function test_leader_profile_settings_are_owner_only_and_validate_a_public_slug_and_introduction(): void
    {
        $leader = $this->leader([
            'public_profile_enabled' => false,
            'public_profile_slug' => null,
            'public_profile_introduction' => null,
        ]);
        $otherLeader = $this->leader(['public_profile_slug' => 'other-leader']);

        $this->actingAs($leader)
            ->get('/leader-hub/profile')
            ->assertOk()
            ->assertSee('Leader profile');

        $this->actingAs($leader)
            ->patch('/leader-hub/profile', [
                'public_profile_enabled' => '1',
                'public_profile_slug' => 'morgan-on-the-trail',
                'public_profile_introduction' => 'I enjoy sharing varied local routes.',
            ])
            ->assertRedirect('/leader-hub/profile');

        $this->assertDatabaseHas('users', [
            'id' => $leader->id,
            'public_profile_enabled' => true,
            'public_profile_slug' => 'morgan-on-the-trail',
            'public_profile_introduction' => 'I enjoy sharing varied local routes.',
        ]);

        $this->actingAs($leader)
            ->patch('/leader-hub/profile', [
                'public_profile_enabled' => '1',
                'public_profile_slug' => 'Morgan On The Trail',
                'public_profile_introduction' => str_repeat('a', 501),
            ])
            ->assertSessionHasErrors(['public_profile_slug', 'public_profile_introduction']);

        $this->actingAs(User::factory()->create())
            ->get('/leader-hub/profile')
            ->assertForbidden();

        $this->actingAs($otherLeader)
            ->patch('/leader-hub/profile', [
                'public_profile_enabled' => '1',
                'public_profile_slug' => 'morgan-on-the-trail',
            ])
            ->assertSessionHasErrors('public_profile_slug');
    }

    public function test_non_leader_profile_updates_are_forbidden_before_slug_validation_can_disclose_availability(): void
    {
        $this->leader(['public_profile_slug' => 'taken-leader-address']);
        $member = User::factory()->create([
            'role' => AccountRole::VerifiedMember,
            'public_profile_slug' => null,
            'public_profile_introduction' => null,
        ]);

        $taken = $this->actingAs($member)->patch('/leader-hub/profile', [
            'public_profile_enabled' => '1',
            'public_profile_slug' => 'taken-leader-address',
        ]);
        $available = $this->actingAs($member)->patch('/leader-hub/profile', [
            'public_profile_enabled' => '1',
            'public_profile_slug' => 'available-leader-address',
        ]);

        $taken->assertForbidden();
        $available->assertForbidden();
        $this->assertSame($taken->getContent(), $available->getContent());
        $this->assertDatabaseHas('users', [
            'id' => $member->id,
            'public_profile_enabled' => false,
            'public_profile_slug' => null,
            'public_profile_introduction' => null,
        ]);
    }

    public function test_leader_hub_continue_draft_link_is_actor_owned_and_groups_walks(): void
    {
        $leader = $this->leader();
        $otherLeader = $this->leader(['public_profile_slug' => 'other-leader']);
        $draft = $this->walkEvent('Own draft walk', $leader, $leader, [], [
            'status' => EventStatus::Draft,
            'is_public' => false,
            'published_at' => null,
        ]);
        $current = $this->walkEvent('Own current walk', $leader, $leader, [], [
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);
        $pending = $this->walkEvent('Own pending walk', $leader, $leader, [], [
            'status' => EventStatus::PendingApproval,
            'is_public' => false,
            'published_at' => null,
        ]);
        $upcoming = $this->walkEvent('Own upcoming walk', $leader, $leader);
        $past = $this->walkEvent('Own past walk', $leader, $leader, [], [
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);
        $otherOwned = $this->walkEvent('Another organisers walk', $otherLeader, $leader);
        $draft->walk->forceFill(['private_organiser_notes' => 'Own private note.'])->save();
        $otherOwned->walk->forceFill(['private_organiser_notes' => 'Other private note.'])->save();

        $this->actingAs($leader)
            ->get('/leader-hub')
            ->assertOk()
            ->assertSee('Leader Hub')
            ->assertSee('Draft walks')
            ->assertSee('Upcoming and current walks')
            ->assertSee('Past walks')
            ->assertSee($draft->title)
            ->assertSee($pending->title)
            ->assertSee($current->title)
            ->assertSee($upcoming->title)
            ->assertSee($past->title)
            ->assertSee('Own private note.')
            ->assertSee('>Continue draft<', false)
            ->assertSee('href="'.WalkResource::getUrl('create', ['draft' => $draft->walk->id]).'"', false)
            ->assertSee('href="'.WalkResource::getUrl('edit', ['record' => $pending->walk]).'">Edit</a>', false)
            ->assertDontSee($otherOwned->title)
            ->assertDontSee('Other private note.');
    }

    public function test_leader_hub_duplicate_creates_an_actor_owned_draft_through_the_existing_action(): void
    {
        $leader = $this->leader();
        $source = $this->walkEvent('Duplicate source walk', $leader, $leader);

        $this->actingAs($leader)
            ->post('/leader-hub/walks/'.$source->walk->id.'/duplicate', [
                'title' => 'Leader hub duplicate',
                'slug' => 'leader-hub-duplicate',
                'starts_at' => now()->addMonth()->format('Y-m-d H:i:s'),
                'ends_at' => now()->addMonth()->addHours(4)->format('Y-m-d H:i:s'),
                'copy_groups' => [DuplicateWalkCopyGroup::CoreDetails->value],
            ])
            ->assertRedirect('/leader-hub');

        $duplicate = Walk::query()
            ->whereHas('event', fn ($events) => $events->where('slug', 'leader-hub-duplicate'))
            ->with('event')
            ->sole();

        $this->assertSame($leader->id, $duplicate->event->organiser_id);
        $this->assertSame(EventStatus::Draft, $duplicate->event->status);
        $this->assertFalse($duplicate->event->is_public);
    }

    public function test_leader_hub_renders_only_shortcuts_usable_under_the_configured_capability_matrix(): void
    {
        $leader = $this->leader();
        $event = $this->walkEvent('Configurable shortcuts walk', $leader, $leader);
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);

        app(ConfigureRoleCapabilities::class)->handle($administrator, AccountRole::WalkLeader, [
            ModuleCapability::AccessAdministration,
            ModuleCapability::ManageOwnWalks,
        ]);

        $this->actingAs($leader)
            ->get('/leader-hub')
            ->assertOk()
            ->assertDontSee('Create walk')
            ->assertSee('>Edit<', false)
            ->assertSee('>Duplicate<', false);

        app(ConfigureRoleCapabilities::class)->handle($administrator, AccountRole::WalkLeader, [
            ModuleCapability::CreateWalks,
            ModuleCapability::ManageOwnWalks,
        ]);

        $this->actingAs($leader)
            ->get('/leader-hub')
            ->assertOk()
            ->assertDontSee('Create walk')
            ->assertDontSee('>Edit<', false)
            ->assertSee('>Duplicate<', false);

        $this->actingAs($leader)
            ->get(route('leader-hub.walks.duplicate.edit', $event->walk))
            ->assertOk();
    }

    public function test_leader_hub_denies_unverified_or_non_capable_accounts_and_keeps_an_administrator_actor_owned(): void
    {
        $unverifiedLeader = $this->leader([
            'public_profile_slug' => 'unverified-leader',
            'email_verified_at' => null,
        ]);
        $member = User::factory()->create(['role' => AccountRole::VerifiedMember]);
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $otherLeader = $this->leader(['public_profile_slug' => 'other-leader']);
        $adminWalk = $this->walkEvent('Administrators own walk', $administrator, $administrator);
        $otherWalk = $this->walkEvent('Another leaders private walk', $otherLeader, $otherLeader);
        $adminWalk->walk->forceFill(['private_organiser_notes' => 'Administrators private note.'])->save();
        $otherWalk->walk->forceFill(['private_organiser_notes' => 'Another leaders private note.'])->save();

        $this->actingAs($unverifiedLeader)->get('/leader-hub')->assertForbidden();
        $this->actingAs($member)->get('/leader-hub')->assertForbidden();

        $this->actingAs($administrator)
            ->get('/leader-hub')
            ->assertOk()
            ->assertSee('Administrators own walk')
            ->assertSee('Administrators private note.')
            ->assertDontSee('Another leaders private walk')
            ->assertDontSee('Another leaders private note.');
    }

    public function test_leader_hub_uses_existing_completion_boundaries_without_mixing_drafts_into_history(): void
    {
        $leader = $this->leader();
        $pastDraft = $this->walkEvent('Past draft stays draft', $leader, $leader, [], [
            'status' => EventStatus::Draft,
            'is_public' => false,
            'published_at' => null,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);
        $overrideCurrent = $this->walkEvent('Reopened past walk', $leader, $leader, [], [
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
            'completion_override' => false,
        ]);
        $completedFuture = $this->walkEvent('Completed future walk', $leader, $leader, [], [
            'status' => EventStatus::Completed,
            'completion_override' => true,
        ]);

        $response = $this->actingAs($leader)->get('/leader-hub')->assertOk();
        $html = $response->getContent();

        $this->assertSame(1, substr_count($html, $pastDraft->title));
        $this->assertSame(1, substr_count($html, $overrideCurrent->title));
        $this->assertSame(1, substr_count($html, $completedFuture->title));
        $this->assertMatchesRegularExpression('/Draft walks.*Past draft stays draft/s', $html);
        $this->assertMatchesRegularExpression('/Upcoming and current walks.*Reopened past walk/s', $html);
        $this->assertMatchesRegularExpression('/Past walks.*Completed future walk/s', $html);
    }

    public function test_leader_surface_has_no_directory_or_photo_upload_or_moderation_action(): void
    {
        $leader = $this->leader();

        $this->get('/leaders')->assertNotFound();
        $this->assertFalse(Route::has('leaders.index'));

        $this->actingAs($leader)
            ->get('/leader-hub/profile')
            ->assertOk()
            ->assertDontSee('type="file"', false)
            ->assertDontSee('Moderate photos');
    }

    /** @param array<string, mixed> $attributes */
    private function leader(array $attributes = []): User
    {
        return User::factory()->create(array_replace([
            'role' => AccountRole::WalkLeader,
            'name' => 'Morgan Private',
            'display_name' => 'Morgan M.',
            'phone' => '07000 111222',
            'public_profile_enabled' => true,
            'public_profile_slug' => 'morgan-m',
            'public_profile_introduction' => 'A friendly local walk leader.',
        ], $attributes));
    }

    /**
     * @param  array<int, User>  $coLeaders
     * @param  array<string, mixed>  $attributes
     */
    private function walkEvent(string $title, User $organiser, User $primaryLeader, array $coLeaders = [], array $attributes = []): Event
    {
        $startsAt = now()->addWeek();
        $event = Event::query()->create(array_replace([
            'type' => EventType::Walk,
            'title' => $title,
            'slug' => str($title)->slug()->toString().'-'.str()->random(6),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(4),
            'status' => EventStatus::Published,
            'is_public' => true,
            'published_at' => now()->subMinute(),
            'organiser_id' => $organiser->id,
        ], $attributes));

        app(SaveWalkDetails::class)->handle($event, [
            'primary_leader_id' => $primaryLeader->id,
            'co_leader_ids' => array_map(fn (User $leader): int => $leader->id, $coLeaders),
        ]);

        return $event->fresh('walk') ?? $event;
    }
}
