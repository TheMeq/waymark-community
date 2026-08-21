<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Models\StaleAccountReview;
use App\Domain\Membership\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class ReviewStaleAccount
{
    public function __construct(private RecordAccountAdministrationAudit $audit) {}

    public function handle(User $actor, StaleAccountReview $review, string $decision): StaleAccountReview
    {
        Gate::forUser($actor)->authorize('reviewStaleAccount', $review);
        if (! in_array($decision, ['leave_alone', 'deactivate', 'reviewed'], true)) {
            throw ValidationException::withMessages(['decision' => 'Choose a valid stale account review action.']);
        }

        return DB::transaction(function () use ($actor, $review, $decision): StaleAccountReview {
            $review = StaleAccountReview::query()->lockForUpdate()->findOrFail($review->id);
            $user = User::query()->lockForUpdate()->findOrFail($review->user_id);
            if ($review->status !== 'pending') {
                throw ValidationException::withMessages(['review' => 'This stale account has already been reviewed.']);
            }
            if ($decision === 'deactivate') {
                $user->forceFill(['account_status' => AccountStatus::Disabled])->save();
            }
            $review->update(['status' => $decision, 'reviewed_by_user_id' => $actor->id, 'reviewed_at' => now()]);
            $this->audit->handle($actor, $user, 'stale_account_'.$decision, ['stale_account_review_id' => $review->id]);

            return $review->fresh();
        });
    }
}
