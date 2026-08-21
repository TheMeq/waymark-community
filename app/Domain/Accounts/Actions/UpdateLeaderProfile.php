<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Data\LeaderProfileData;
use App\Models\User;

final class UpdateLeaderProfile
{
    public function handle(User $leader, LeaderProfileData $profile): void
    {
        $leader->forceFill([
            'public_profile_enabled' => $profile->isPublic,
            'public_profile_slug' => $profile->slug,
            'public_profile_introduction' => $profile->introduction,
        ])->save();
    }
}
