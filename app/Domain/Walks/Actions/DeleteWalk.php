<?php

namespace App\Domain\Walks\Actions;

use App\Domain\Events\Actions\ChangeEventStatus;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Walks\Models\Walk;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class DeleteWalk
{
    public function __construct(private ChangeEventStatus $changeStatus) {}

    public function handle(Walk $walk, User $actor): bool
    {
        return DB::transaction(function () use ($walk, $actor): bool {
            $locked = Walk::query()->lockForUpdate()->findOrFail($walk->id);
            $locked->setRelation('event', $locked->event()->lockForUpdate()->firstOrFail());
            Gate::forUser($actor)->authorize('delete', $locked);
            $locked->event->forceFill(['is_public' => false, 'published_at' => null])->save();
            $this->changeStatus->handle($locked->event, EventStatus::Archived, $actor);

            if (! $locked->delete()) {
                throw ValidationException::withMessages(['walk' => 'The walk could not be deleted.']);
            }

            return true;
        });
    }
}
