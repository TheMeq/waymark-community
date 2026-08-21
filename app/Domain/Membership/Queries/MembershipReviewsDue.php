<?php

namespace App\Domain\Membership\Queries;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class MembershipReviewsDue
{
    /** @return Collection<int, User> */
    public function handle(): Collection
    {
        return User::query()
            ->whereNotNull('membership_review_due_at')
            ->where('membership_review_due_at', '<=', now(config('app.timezone'))->toDateString())
            ->orderBy('membership_review_due_at')
            ->orderBy('id')
            ->get();
    }
}
