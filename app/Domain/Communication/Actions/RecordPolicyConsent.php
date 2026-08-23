<?php

namespace App\Domain\Communication\Actions;

use App\Domain\Communication\Models\PolicyConsent;
use App\Domain\Communication\Models\PolicyVersion;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class RecordPolicyConsent
{
    public function handle(User $user, PolicyVersion $version, string $action): PolicyConsent
    {
        if ($version->publication_state !== 'published' || $version->published_at === null || ! in_array($action, ['newsletter', 'photo_upload', 'terms'], true)) {
            throw ValidationException::withMessages(['consent' => 'Consent must reference a published relevant policy version.']);
        }

        return PolicyConsent::query()->updateOrCreate(['user_id' => $user->id, 'policy_version_id' => $version->id, 'action' => $action], ['accepted_at' => now(), 'withdrawn_at' => null]);
    }
}
