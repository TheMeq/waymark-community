<?php

namespace App\Domain\Governance\Queries;

use App\Domain\Governance\Models\Document;
use Illuminate\Database\Eloquent\Collection;

final class LeaderHubDocuments
{
    /** @return Collection<int, Document> */
    public function get(): Collection
    {
        return Document::query()
            ->with(['category', 'currentVersion'])
            ->where('visibility', 'leader')
            ->where('approval_status', 'approved')
            ->whereNotNull('current_version_id')
            ->where(fn ($query) => $query->whereNull('publication_date')->orWhere('publication_date', '<=', today()))
            ->whereHas('currentVersion', fn ($query) => $query->whereNotNull('published_at')->where('published_at', '<=', now()))
            ->orderBy('title')
            ->get();
    }
}
