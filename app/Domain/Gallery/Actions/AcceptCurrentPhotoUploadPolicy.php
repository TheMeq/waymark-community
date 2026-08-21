<?php

namespace App\Domain\Gallery\Actions;

use App\Domain\Gallery\Models\PhotoPolicyAcceptance;
use App\Models\User;

final class AcceptCurrentPhotoUploadPolicy
{
    public function handle(User $account): PhotoPolicyAcceptance
    {
        return PhotoPolicyAcceptance::query()->firstOrCreate(
            [
                'user_id' => $account->id,
                'policy_version' => (string) config('gallery.photo_policy.current_version'),
            ],
            ['accepted_at' => now()],
        );
    }
}
