<?php

namespace App\Domain\Governance\Actions;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Governance\Models\Document;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class ApproveControlledDocument
{
    public function handle(User $actor, Document $document): Document
    {
        if (! $actor->hasCapability(ModuleCapability::ManageGovernance) || ! $document->controlled) {
            throw ValidationException::withMessages(['approval' => 'Only governance managers may approve controlled documents.']);
        }
        $document->update(['approval_status' => 'approved', 'approver_id' => $actor->id, 'approved_at' => now()]);

        return $document->refresh();
    }
}
