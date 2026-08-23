<?php

namespace App\Domain\Governance\Actions;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Governance\Models\Document;
use App\Domain\Governance\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PublishDocumentVersion
{
    public function handle(User $actor, Document $document, DocumentVersion $version): Document
    {
        if (! $actor->hasCapability(ModuleCapability::ManageGovernance) || ! $version->document->is($document) || ($document->controlled && $document->approval_status !== 'approved')) {
            throw ValidationException::withMessages(['version' => 'This document version cannot be published.']);
        }

        return DB::transaction(function () use ($document, $version): Document {
            $version->update(['published_at' => now()]);
            $document->update(['current_version_id' => $version->id]);

            return $document->refresh();
        });
    }
}
