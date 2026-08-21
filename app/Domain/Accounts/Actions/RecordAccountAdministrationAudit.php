<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Models\AccountAdministrationAudit;
use App\Models\User;

final class RecordAccountAdministrationAudit
{
    /** @param array<string, scalar|null> $context */
    public function handle(User $actor, ?User $subject, string $action, array $context = []): AccountAdministrationAudit
    {
        return AccountAdministrationAudit::query()->create([
            'actor_user_id' => $actor->getKey(),
            'subject_user_id' => $subject?->getKey(),
            'action' => $action,
            'context' => $context,
        ]);
    }
}
