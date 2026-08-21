<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Membership\Enums\MembershipStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class RecordMembershipVerification
{
    public function handle(
        User $actor,
        User $account,
        MembershipStatus|string $status,
        ?string $source,
        ?string $reviewDueAt,
    ): void {
        if (! $actor->hasCapability(ModuleCapability::ManageMembershipVerification)) {
            throw new AuthorizationException;
        }

        $validated = Validator::validate([
            'status' => $status instanceof MembershipStatus ? $status->value : $status,
            'source' => $source,
            'review_due_at' => $reviewDueAt,
        ], [
            'status' => ['required', Rule::enum(MembershipStatus::class)],
            'source' => ['nullable', 'string', 'max:255'],
            'review_due_at' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $reviewDueAt = $validated['review_due_at'] === null
            ? null
            : Carbon::createFromFormat('Y-m-d', $validated['review_due_at'])->toDateString();

        $recordedAt = now();

        DB::transaction(function () use ($account, $actor, $validated, $reviewDueAt, $recordedAt): void {
            User::query()->whereKey($account->getKey())->update([
                'membership_status' => $validated['status'],
                'membership_verified_by_user_id' => $actor->id,
                'membership_verified_at' => $recordedAt,
                'membership_verification_source' => $validated['source'],
                'membership_review_due_at' => $reviewDueAt,
                'updated_at' => $recordedAt,
            ]);
        });
    }
}
