<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Models\StaleAccountReview;
use App\Domain\Membership\Enums\AccountStatus;
use App\Models\User;

final class FlagStaleAccountsForReview
{
    public function handle(int $days = 365, int $limit = 100): int
    {
        $threshold = now()->subDays($days);
        $count = 0;

        User::query()->where('account_status', AccountStatus::Active->value)
            ->whereNotNull('last_active_at')
            ->where('last_active_at', '<=', $threshold)
            ->orderBy('id')->limit($limit)->each(function (User $user) use (&$count): void {
                $review = StaleAccountReview::query()->firstOrCreate(['user_id' => $user->id], [
                    'status' => 'pending', 'flagged_at' => now(),
                ]);
                if ($review->wasRecentlyCreated) {
                    $count++;
                }
            });

        return $count;
    }
}
