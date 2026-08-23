<?php

namespace App\Domain\Governance\Queries;

use App\Domain\Governance\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final readonly class LeaderHubDocuments
{
    public function __construct(private AvailableDocuments $available) {}

    /** @return Collection<int, Document> */
    public function get(User $leader): Collection
    {
        return $this->available->query($leader)
            ->with(['category', 'currentVersion'])
            ->where('visibility', 'leader')
            ->orderBy('title')
            ->get();
    }
}
