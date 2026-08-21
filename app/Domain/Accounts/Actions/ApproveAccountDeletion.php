<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Models\AccountDeletionRequest;
use App\Domain\Accounts\Models\PersonalDataExport;
use App\Domain\Membership\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class ApproveAccountDeletion
{
    public function __construct(
        private RecordAccountAdministrationAudit $audit,
        private CleanUpPersonalDataExports $cleanup,
    ) {}

    public function handle(User $actor, AccountDeletionRequest $request, ?string $reviewNote = null): AccountDeletionRequest
    {
        Gate::forUser($actor)->authorize('reviewAccountDeletion', $request);
        $requestUserId = $request->user_id;
        $exportIds = [];

        $request = DB::transaction(function () use ($actor, $request, $requestUserId, $reviewNote, &$exportIds): AccountDeletionRequest {
            $user = User::query()->lockForUpdate()->findOrFail($requestUserId);
            $request = AccountDeletionRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($request->status !== 'requested') {
                throw ValidationException::withMessages(['request' => 'This deletion request has already been reviewed.']);
            }

            if ($user->isInstallationOwner() || $user->role === AccountRole::Administrator) {
                throw ValidationException::withMessages(['account' => 'Installation owners and administrator accounts cannot be anonymised through this workflow.']);
            }

            PersonalDataExport::query()->where('user_id', $user->id)->lockForUpdate()->get()->each(function (PersonalDataExport $export) use (&$exportIds): void {
                $exportIds[] = $export->id;
                $export->update([
                    'status' => 'revoked', 'storage_path' => $export->hasSafeStoragePath() ? $export->storage_path : null,
                    'download_token' => null, 'download_token_hash' => null,
                    'processing_started_at' => null, 'expires_at' => null,
                ]);
            });

            $user->communicationPreferences()->delete();
            $user->favourites()->delete();
            $user->forceFill([
                'name' => 'Deleted member',
                'email' => 'deleted-'.$user->id.'-'.Str::lower(Str::random(20)).'@deleted.invalid',
                'display_name' => null,
                'phone' => null,
                'profile_photo_reference' => null,
                'public_profile_enabled' => false,
                'public_profile_slug' => null,
                'public_profile_introduction' => null,
                'account_status' => AccountStatus::Disabled,
                'membership_status' => 'unverified',
                'membership_verified_by_user_id' => null,
                'membership_verified_at' => null,
                'membership_verification_source' => null,
                'membership_review_due_at' => null,
                'password' => Str::random(64),
                'remember_token' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();

            $request->update([
                'status' => 'approved', 'reviewed_by_user_id' => $actor->id, 'review_note' => $reviewNote,
                'reviewed_at' => now(), 'completed_at' => now(),
            ]);
            $this->audit->handle($actor, $user, 'account_deletion_approved', ['deletion_request_id' => $request->id]);

            return $request->fresh();
        });

        foreach ($exportIds as $exportId) {
            $this->cleanup->handleExport($exportId);
        }

        return $request;
    }
}
