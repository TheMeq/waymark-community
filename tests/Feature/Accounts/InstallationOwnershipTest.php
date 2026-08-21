<?php

namespace Tests\Feature\Accounts;

use App\Domain\Accounts\Actions\CreateAdministrator;
use App\Domain\Accounts\Actions\EstablishInitialInstallationOwner;
use App\Domain\Accounts\Actions\PromoteToAdministrator;
use App\Domain\Accounts\Actions\TransferInstallationOwnership;
use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Accounts\Models\AccountAdministrationAudit;
use App\Domain\Accounts\Models\InstallationOwnership;
use App\Domain\Accounts\Notifications\AdministratorAccessChanged;
use App\Domain\Accounts\Notifications\InstallationOwnershipTransferred;
use App\Filament\Pages\AccountAdministration;
use App\Http\Middleware\RequireSensitiveActionAssurance;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class InstallationOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_administrator_establishes_the_single_transferable_installation_owner(): void
    {
        $initialAdministrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $laterAdministrator = User::factory()->create(['role' => AccountRole::Administrator]);

        $firstOwnership = app(EstablishInitialInstallationOwner::class)->handle($initialAdministrator);
        $secondOwnership = app(EstablishInitialInstallationOwner::class)->handle($laterAdministrator);

        $this->assertSame($initialAdministrator->id, $firstOwnership->owner_user_id);
        $this->assertSame($initialAdministrator->id, $secondOwnership->owner_user_id);
        $this->assertSame(1, InstallationOwnership::query()->count());
    }

    public function test_a_non_one_singleton_identity_remains_operable_and_cannot_be_joined_by_a_second_row(): void
    {
        $owner = User::factory()->create(['role' => AccountRole::Administrator]);
        $newOwner = User::factory()->create(['role' => AccountRole::Administrator]);

        DB::table('installation_ownerships')->insert([
            'id' => 2,
            'owner_user_id' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(TransferInstallationOwnership::class)->handle($owner, $newOwner);

        $this->assertSame($newOwner->id, InstallationOwnership::query()->sole()->owner_user_id);

        $this->expectException(QueryException::class);

        DB::table('installation_ownerships')->insert([
            'id' => 3,
            'owner_user_id' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_ownership_transfer_is_atomic_audited_and_notifies_both_owners_without_changing_the_previous_owners_role(): void
    {
        Notification::fake();

        $previousOwner = $this->initialAdministrator();
        $newOwner = User::factory()->create(['role' => AccountRole::Administrator]);

        app(TransferInstallationOwnership::class)->handle($previousOwner, $newOwner);

        $ownership = InstallationOwnership::query()->sole();
        $this->assertSame($newOwner->id, $ownership->owner_user_id);
        $this->assertSame(AccountRole::Administrator, $previousOwner->fresh()->role);
        $this->assertSame(1, AccountAdministrationAudit::query()
            ->where('action', 'installation_ownership_transferred')
            ->where('actor_user_id', $previousOwner->id)
            ->where('subject_user_id', $newOwner->id)
            ->count());

        Notification::assertSentTo($previousOwner, InstallationOwnershipTransferred::class);
        Notification::assertSentTo($newOwner, InstallationOwnershipTransferred::class);
    }

    #[DataProvider('invalidOwnerTargetProvider')]
    public function test_ownership_transfer_rejects_invalid_targets_and_preserves_the_existing_owner(array $attributes): void
    {
        $owner = $this->initialAdministrator();
        $target = User::factory()->create(array_replace(['role' => AccountRole::Administrator], $attributes));

        try {
            app(TransferInstallationOwnership::class)->handle($owner, $target);
            $this->fail('An invalid ownership target was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('owner', $exception->errors());
        }

        $this->assertSame($owner->id, InstallationOwnership::query()->sole()->owner_user_id);
    }

    public function test_an_administrator_who_is_not_the_owner_cannot_transfer_ownership(): void
    {
        $owner = $this->initialAdministrator();
        $otherAdministrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $target = User::factory()->create(['role' => AccountRole::Administrator]);

        try {
            app(TransferInstallationOwnership::class)->handle($otherAdministrator, $target);
            $this->fail('A non-owner administrator transferred installation ownership.');
        } catch (AuthorizationException) {
            // Expected: ownership is a responsibility, not a general administrator power.
        }

        $this->assertSame($owner->id, InstallationOwnership::query()->sole()->owner_user_id);
    }

    public function test_owner_responsibility_does_not_bypass_the_fixed_capability_matrix(): void
    {
        $owner = $this->initialAdministrator();
        $owner->forceFill(['role' => AccountRole::RegisteredUser, 'is_admin' => true])->save();
        $target = User::factory()->create(['role' => AccountRole::Administrator]);

        $this->assertFalse($owner->fresh()->hasCapability(ModuleCapability::ManageAccounts));

        try {
            app(TransferInstallationOwnership::class)->handle($owner->fresh(), $target);
            $this->fail('Installation ownership bypassed the fixed capability matrix.');
        } catch (AuthorizationException) {
            // Expected: ownership does not grant capabilities.
        }

        $this->assertSame($owner->id, InstallationOwnership::query()->sole()->owner_user_id);
    }

    public function test_administrator_promotion_assigns_only_the_fixed_administrator_role_and_notifies_existing_administrators(): void
    {
        Notification::fake();

        $actor = $this->initialAdministrator();
        $existingAdministrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $member = User::factory()->create(['role' => null, 'is_admin' => false]);

        app(PromoteToAdministrator::class)->handle($actor, $member);

        $this->assertSame(AccountRole::Administrator, $member->fresh()->role);
        $this->assertSame(1, AccountAdministrationAudit::query()
            ->where('action', 'administrator_promoted')
            ->where('actor_user_id', $actor->id)
            ->where('subject_user_id', $member->id)
            ->count());
        Notification::assertSentTo($actor, AdministratorAccessChanged::class);
        Notification::assertSentTo($existingAdministrator, AdministratorAccessChanged::class);
        Notification::assertNotSentTo($member, AdministratorAccessChanged::class);
    }

    public function test_administrator_promotion_rejects_self_inactive_and_unverified_targets(): void
    {
        $actor = $this->initialAdministrator();
        $inactive = User::factory()->create([
            'role' => AccountRole::RegisteredUser,
            'account_status' => 'disabled',
        ]);
        $unverified = User::factory()->unverified()->create(['role' => AccountRole::RegisteredUser]);

        foreach ([$actor, $inactive, $unverified] as $target) {
            try {
                app(PromoteToAdministrator::class)->handle($actor, $target);
                $this->fail('An invalid administrator promotion target was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('account', $exception->errors());
            }
        }
    }

    public function test_administrator_creation_uses_a_password_reset_and_notifies_existing_administrators(): void
    {
        Notification::fake();

        $actor = $this->initialAdministrator();
        $existingAdministrator = User::factory()->create(['role' => AccountRole::Administrator]);

        $created = app(CreateAdministrator::class)->handle($actor, 'New Administrator', 'new-administrator@example.test');

        $this->assertSame(AccountRole::Administrator, $created->role);
        $this->assertNull($created->email_verified_at);
        $this->assertSame(1, AccountAdministrationAudit::query()
            ->where('action', 'administrator_created')
            ->where('actor_user_id', $actor->id)
            ->where('subject_user_id', $created->id)
            ->count());
        Notification::assertSentTo($created, ResetPassword::class);
        Notification::assertSentTo($actor, AdministratorAccessChanged::class);
        Notification::assertSentTo($existingAdministrator, AdministratorAccessChanged::class);
    }

    public function test_account_administration_page_requires_sensitive_assurance_and_a_fresh_second_factor_when_enabled(): void
    {
        $administrator = $this->initialAdministrator();

        $this->actingAs($administrator)
            ->get('/admin/account-administration')
            ->assertRedirect(route('password.confirm'));

        $this->withSession([
            'auth.password_confirmed_at' => now()->unix(),
            'sensitive.password_confirmed_user_id' => $administrator->id,
        ])
            ->get('/admin/account-administration')
            ->assertOk()
            ->assertSee('Account administration');

        $administrator->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt('two-factor-secret'),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['recovery-code'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->withSession([
            'auth.password_confirmed_at' => now()->unix(),
            'sensitive.password_confirmed_user_id' => $administrator->id,
        ])
            ->get('/admin/account-administration')
            ->assertRedirect(route('account.sensitive-confirmation.create'));
    }

    public function test_sensitive_assurance_remains_persistent_for_account_administration_livewire_requests(): void
    {
        $this->assertContains(RequireSensitiveActionAssurance::class, Livewire::getPersistentMiddleware());
    }

    public function test_account_administration_livewire_save_cannot_transfer_ownership_without_recent_password_assurance(): void
    {
        Notification::fake();

        $owner = $this->initialAdministrator();
        $newOwner = User::factory()->create(['role' => AccountRole::Administrator]);

        $this->actingAs($owner)->withSession([]);

        Livewire::test(AccountAdministration::class)
            ->set('data.operation', 'transfer')
            ->set('data.transfer_to_user_id', $newOwner->id)
            ->call('save')
            ->assertForbidden();

        $this->assertSame($owner->id, InstallationOwnership::query()->sole()->owner_user_id);
        $this->assertSame(0, AccountAdministrationAudit::query()->count());
        Notification::assertNothingSent();
    }

    public function test_account_administration_livewire_save_rejects_expired_password_assurance_without_mutating_ownership(): void
    {
        Notification::fake();

        $owner = $this->initialAdministrator();
        $newOwner = User::factory()->create(['role' => AccountRole::Administrator]);

        $this->actingAs($owner);
        app('session.store')->put([
            'auth.password_confirmed_at' => now()->subSeconds(config('security.sensitive_action_timeout') + 1)->unix(),
            'sensitive.password_confirmed_user_id' => $owner->id,
        ]);

        Livewire::test(AccountAdministration::class)
            ->set('data.operation', 'transfer')
            ->set('data.transfer_to_user_id', $newOwner->id)
            ->call('save')
            ->assertForbidden();

        $this->assertSame($owner->id, InstallationOwnership::query()->sole()->owner_user_id);
        $this->assertSame(0, AccountAdministrationAudit::query()->count());
        Notification::assertNothingSent();
    }

    public function test_account_administration_livewire_save_cannot_promote_or_create_without_recent_password_assurance(): void
    {
        Notification::fake();

        $owner = $this->initialAdministrator();
        $member = User::factory()->create(['role' => AccountRole::RegisteredUser]);

        $this->actingAs($owner)->withSession([]);

        Livewire::test(AccountAdministration::class)
            ->set('data.operation', 'promote')
            ->set('data.promote_user_id', $member->id)
            ->call('save')
            ->assertForbidden();

        Livewire::test(AccountAdministration::class)
            ->set('data.operation', 'create')
            ->set('data.new_administrator_name', 'Blocked Administrator')
            ->set('data.new_administrator_email', 'blocked-administrator@example.test')
            ->call('save')
            ->assertForbidden();

        $this->assertSame(AccountRole::RegisteredUser, $member->fresh()->role);
        $this->assertDatabaseMissing('users', ['email' => 'blocked-administrator@example.test']);
        $this->assertSame(0, AccountAdministrationAudit::query()->count());
        Notification::assertNothingSent();
    }

    public function test_account_administration_livewire_save_requires_fresh_two_factor_assurance_and_then_transfers_with_both_confirmations(): void
    {
        Notification::fake();

        $owner = $this->initialAdministrator();
        $owner->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt('two-factor-secret'),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['recovery-code'])),
            'two_factor_confirmed_at' => now(),
        ])->save();
        $newOwner = User::factory()->create(['role' => AccountRole::Administrator]);

        $this->actingAs($owner);
        app('session.store')->put([
            'auth.password_confirmed_at' => now()->unix(),
            'sensitive.password_confirmed_user_id' => $owner->id,
        ]);

        Livewire::test(AccountAdministration::class)
            ->set('data.operation', 'transfer')
            ->set('data.transfer_to_user_id', $newOwner->id)
            ->call('save')
            ->assertForbidden();

        $this->assertSame($owner->id, InstallationOwnership::query()->sole()->owner_user_id);
        $this->assertSame(0, AccountAdministrationAudit::query()->count());

        app('session.store')->put([
            'sensitive.two_factor_confirmed_at' => now()->unix(),
            'sensitive.two_factor_confirmed_user_id' => $owner->id,
        ]);

        Livewire::test(AccountAdministration::class)
            ->set('data.operation', 'transfer')
            ->set('data.transfer_to_user_id', $newOwner->id)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($newOwner->id, InstallationOwnership::query()->sole()->owner_user_id);
        $this->assertSame(1, AccountAdministrationAudit::query()->count());
        Notification::assertSentTo($owner, InstallationOwnershipTransferred::class);
        Notification::assertSentTo($newOwner, InstallationOwnershipTransferred::class);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalidOwnerTargetProvider(): array
    {
        return [
            'inactive administrator' => [['account_status' => 'disabled']],
            'unverified administrator' => [['email_verified_at' => null]],
            'non-administrator' => [['role' => AccountRole::RegisteredUser]],
        ];
    }

    private function initialAdministrator(): User
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        app(EstablishInitialInstallationOwner::class)->handle($administrator);

        return $administrator;
    }
}
