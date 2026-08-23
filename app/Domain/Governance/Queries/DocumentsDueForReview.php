<?php

namespace App\Domain\Governance\Queries;

use App\Domain\Governance\Models\Document;
use Illuminate\Support\Collection;

final class DocumentsDueForReview
{
    public function get(int $withinDays = 30): Collection
    {
        return Document::query()->where('controlled', true)->whereNotNull('review_date')->whereDate('review_date', '<=', now()->addDays($withinDays))->orderBy('review_date')->get();
    }
}
