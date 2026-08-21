<?php

namespace App\Domain\Gallery;

use App\Domain\Gallery\Models\PhotoPolicyAcceptance;
use App\Models\User;

final class PhotoUploadPolicyGate
{
    public function for(User $account): PhotoUploadPolicyDecision
    {
        if (! $account->isActive()) {
            return PhotoUploadPolicyDecision::AccountInactive;
        }

        if (! $account->hasVerifiedEmail()) {
            return PhotoUploadPolicyDecision::EmailVerificationRequired;
        }

        $acceptances = PhotoPolicyAcceptance::query()->where('user_id', $account->id);

        if (! $acceptances->exists()) {
            return PhotoUploadPolicyDecision::PolicyAcceptanceRequired;
        }

        if (! $acceptances
            ->where('policy_version', (string) config('gallery.photo_policy.current_version'))
            ->exists()) {
            return PhotoUploadPolicyDecision::PolicyVersionAcceptanceRequired;
        }

        return PhotoUploadPolicyDecision::UploadAllowedWithReminder;
    }
}
