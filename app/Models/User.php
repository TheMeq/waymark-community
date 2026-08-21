<?php

namespace App\Models;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Accounts\Models\CommunicationPreference;
use App\Domain\Accounts\Models\RoleCapability;
use App\Domain\Membership\Enums\AccountStatus;
use App\Domain\Membership\Enums\MembershipStatus;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable([
    'name',
    'display_name',
    'email',
    'phone',
    'password',
    'can_manage_walks',
    'public_profile_enabled',
    'public_profile_slug',
    'public_profile_introduction',
])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /** @var array<string, mixed> */
    protected $attributes = [
        'account_status' => 'active',
        'membership_status' => 'unverified',
        'is_admin' => false,
        'can_manage_walks' => false,
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin'
            && $this->hasCapability(ModuleCapability::AccessAdministration)
            && (! $this->isWalkLeader() || $this->hasVerifiedEmail());
    }

    public function hasCapability(ModuleCapability $capability): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->role !== null) {
            return $this->hasRoleCapability($this->role, $capability);
        }

        if ($this->hasLegacyCapability($capability)) {
            return true;
        }

        return $this->hasRoleCapability(AccountRole::RegisteredUser, $capability);
    }

    public function isActive(): bool
    {
        return $this->account_status === AccountStatus::Active;
    }

    private function hasRoleCapability(AccountRole $role, ModuleCapability $capability): bool
    {
        return RoleCapability::query()
            ->where('role', $role->value)
            ->where('capability', $capability->value)
            ->exists();
    }

    public function publicDisplayName(): string
    {
        $displayName = trim((string) $this->display_name);

        if ($displayName !== '') {
            return $displayName;
        }

        $nameParts = preg_split('/\s+/', trim($this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($nameParts === []) {
            return 'Member';
        }

        if (count($nameParts) === 1) {
            return $nameParts[0];
        }

        return $nameParts[0].' '.mb_strtoupper(mb_substr((string) end($nameParts), 0, 1)).'.';
    }

    public function hasPublicLeaderProfile(): bool
    {
        return $this->public_profile_enabled
            && filled($this->public_profile_slug)
            && $this->isEligibleWalkLeader();
    }

    public function publicLeaderProfileUrl(): ?string
    {
        if (! $this->hasPublicLeaderProfile()) {
            return null;
        }

        return route('leaders.show', $this->public_profile_slug);
    }

    public function isEligibleWalkLeader(): bool
    {
        return $this->isActive()
            && $this->hasVerifiedEmail()
            && $this->isWalkLeader();
    }

    /** @return HasMany<CommunicationPreference, $this> */
    public function communicationPreferences(): HasMany
    {
        return $this->hasMany(CommunicationPreference::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_status' => AccountStatus::class,
            'membership_status' => MembershipStatus::class,
            'role' => AccountRole::class,
            'is_admin' => 'boolean',
            'can_manage_walks' => 'boolean',
            'email_verified_at' => 'datetime',
            'membership_verified_at' => 'datetime',
            'membership_review_due_at' => 'date',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    private function isWalkLeader(): bool
    {
        return $this->role === AccountRole::WalkLeader
            || ($this->role === null && ! $this->is_admin && $this->can_manage_walks);
    }

    private function hasLegacyCapability(ModuleCapability $capability): bool
    {
        if ($this->is_admin) {
            return true;
        }

        return $this->can_manage_walks && in_array($capability, [
            ModuleCapability::AccessAdministration,
            ModuleCapability::CreateWalks,
            ModuleCapability::ManageOwnWalks,
            ModuleCapability::ManageOwnEventUpdates,
        ], true);
    }
}
