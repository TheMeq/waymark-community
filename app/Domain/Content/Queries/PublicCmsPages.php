<?php

namespace App\Domain\Content\Queries;

use App\Domain\Content\Models\CmsPage;
use Illuminate\Database\Eloquent\Builder;

final class PublicCmsPages
{
    public function query(): Builder
    {
        return CmsPage::query()
            ->where('publication_state', 'published')
            ->whereNull('archived_at')
            ->where(fn (Builder $query) => $query->whereNull('publish_at')->orWhere('publish_at', '<=', now()));
    }
}
