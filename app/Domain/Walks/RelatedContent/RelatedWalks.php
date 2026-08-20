<?php

namespace App\Domain\Walks\RelatedContent;

use App\Domain\Events\Models\Event;
use Illuminate\Database\Eloquent\Collection;

interface RelatedWalks
{
    /**
     * This boundary permits a future administrator-curated implementation
     * without changing public walk presentation.
     *
     * @return Collection<int, Event>
     */
    public function for(Event $event, int $limit = 3): Collection;
}
