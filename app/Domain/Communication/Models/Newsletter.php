<?php

namespace App\Domain\Communication\Models;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Membership\Enums\AccountStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['title', 'subject', 'body', 'status', 'scheduled_for', 'sent_at', 'audience_roles', 'audience_account_statuses', 'consent_required', 'consent_policy_version_id', 'created_by_user_id'])]
final class Newsletter extends Model
{
    protected static function booted(): void
    {
        self::saving(function (self $newsletter): void {
            $roles = array_column(AccountRole::cases(), 'value');
            $statuses = array_column(AccountStatus::cases(), 'value');
            if (! in_array($newsletter->status, ['draft', 'scheduled', 'sent'], true) || array_diff((array) $newsletter->audience_roles, $roles) !== [] || array_diff((array) $newsletter->audience_account_statuses, $statuses) !== [] || ($newsletter->consent_required && $newsletter->consent_policy_version_id === null)) {
                throw ValidationException::withMessages(['audience' => 'Choose approved newsletter status, audience and consent settings.']);
            }
        });
    }

    protected function casts(): array
    {
        return ['scheduled_for' => 'datetime', 'sent_at' => 'datetime', 'audience_roles' => 'array', 'audience_account_statuses' => 'array', 'consent_required' => 'boolean'];
    }

    public function consentPolicyVersion(): BelongsTo
    {
        return $this->belongsTo(PolicyVersion::class, 'consent_policy_version_id');
    }
}
