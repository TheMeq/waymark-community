<?php

namespace App\Domain\Communication\Actions;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Communication\Models\PolicyVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PublishPolicyVersion
{
    public function handle(User $actor, PolicyVersion $version): PolicyVersion
    {
        if (! $actor->hasCapability(ModuleCapability::ManageCommunications)) {
            throw ValidationException::withMessages(['policy' => 'You are not allowed to publish policy versions.']);
        }

        return DB::transaction(function () use ($version): PolicyVersion {
            $version->update(['publication_state' => 'published', 'published_at' => now()]);
            $version->page()->update(['current_version_id' => $version->id]);

            return $version->refresh();
        });
    }
}
