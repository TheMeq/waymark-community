<?php

namespace App\Domain\Governance\Queries;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Governance\Models\Document;
use App\Domain\Governance\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class AvailableDocuments
{
    /** @return Builder<Document> */
    public function query(?User $actor = null): Builder
    {
        return Document::query()
            ->whereIn('visibility', $this->audiences($actor))
            ->whereNotNull('current_version_id')
            ->where(fn (Builder $query) => $query->whereNull('publication_date')->orWhere('publication_date', '<=', today()))
            ->where(fn (Builder $query) => $query->where('controlled', false)->orWhere('approval_status', 'approved'))
            ->whereHas('currentVersion', fn (Builder $query) => $query->whereNotNull('published_at')->where('published_at', '<=', now()));
    }

    public function findBySlug(string $slug, ?User $actor = null): Document
    {
        return $this->query($actor)->where('slug', $slug)->firstOrFail();
    }

    public function find(int $id, ?User $actor = null): Document
    {
        return $this->query($actor)->findOrFail($id);
    }

    public function documentForVersion(string $slug, DocumentVersion $version, ?User $actor = null): Document
    {
        $document = $this->findBySlug($slug, $actor);
        abort_unless($version->document_id === $document->id, 404);

        if ($version->id !== $document->current_version_id) {
            abort_unless(
                $document->visibility === 'public'
                && $document->public_version_history
                && $version->published_at !== null
                && $version->published_at->lte(now()),
                404,
            );
        }

        return $document;
    }

    /** @return list<string> */
    private function audiences(?User $actor): array
    {
        $audiences = ['public'];
        if (! $actor instanceof User || ! $actor->isActive()) {
            return $audiences;
        }

        $audiences[] = 'registered';
        if ($actor->hasCapability(ModuleCapability::ManageOwnWalks) || $actor->hasCapability(ModuleCapability::ManageAllWalks)) {
            $audiences[] = 'leader';
        }
        if ($actor->hasCapability(ModuleCapability::AccessCommitteeHub)) {
            $audiences[] = 'committee';
        }

        return $audiences;
    }
}
