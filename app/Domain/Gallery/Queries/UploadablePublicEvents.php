<?php

namespace App\Domain\Gallery\Queries;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Models\Event;
use Illuminate\Database\Eloquent\Builder;

final class UploadablePublicEvents
{
    /** @return Builder<Event> */
    public function query(): Builder
    {
        return Event::query()->where('is_public', true)->whereNotNull('published_at')->where('published_at', '<=', now())
            ->whereIn('status', [EventStatus::Published, EventStatus::Changed, EventStatus::Postponed, EventStatus::Cancelled, EventStatus::Completed]);
    }

    public function find(int $id): ?Event
    {
        return $this->query()->find($id);
    }
}
