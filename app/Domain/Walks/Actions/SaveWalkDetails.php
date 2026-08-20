<?php

namespace App\Domain\Walks\Actions;

use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Walks\Data\StoredGpx;
use App\Domain\Walks\Data\WalkDetailsData;
use App\Domain\Walks\Models\Walk;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveWalkDetails
{
    /** @param array<string, mixed> $attributes */
    public function handle(Event $event, array $attributes, ?StoredGpx $storedGpx = null): Walk
    {
        if ($event->type !== EventType::Walk) {
            throw ValidationException::withMessages([
                'event' => 'Walk details can only be assigned to a walk event.',
            ]);
        }

        $details = WalkDetailsData::from($attributes);

        return DB::transaction(function () use ($event, $details, $storedGpx): Walk {
            $walk = Walk::query()->firstOrNew(['event_id' => $event->id]);
            $walk->fill($details->persistenceAttributes());
            if ($storedGpx !== null) {
                $walk->forceFill($storedGpx->persistenceAttributes());
            }
            $walk->save();
            $walk->coLeaders()->sync($details->coLeaderIds);
            $walk->tags()->sync($details->tagIds);

            return $walk->refresh()->load(['event', 'grade', 'primaryLeader', 'coLeaders', 'tags']);
        });
    }
}
