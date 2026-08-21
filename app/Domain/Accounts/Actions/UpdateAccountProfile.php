<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Data\AccountProfileData;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateAccountProfile
{
    public function handle(User $user, AccountProfileData $profile): void
    {
        DB::transaction(function () use ($user, $profile): void {
            $user->forceFill([
                'name' => $profile->name,
                'display_name' => $profile->displayName,
                'phone' => $profile->phone,
            ])->save();

            foreach ($profile->preferences as $category => $isSubscribed) {
                $preference = $user->communicationPreferences()->firstOrNew([
                    'category' => $category,
                ]);

                $consentedAt = match (true) {
                    ! $isSubscribed => null,
                    ! $preference->exists, ! $preference->is_subscribed => now(),
                    default => $preference->consented_at,
                };

                $preference->fill([
                    'is_subscribed' => $isSubscribed,
                    'consented_at' => $consentedAt,
                ])->save();
            }
        });
    }
}
