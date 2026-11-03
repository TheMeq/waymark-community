<?php

namespace App\Domain\Walks\Actions;

use App\Domain\Events\Actions\ChangeEventStatus;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Walks\Models\Walk;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class UnpublishWalk
{
    public function __construct(private ChangeEventStatus $changeStatus) {}

    public function handle(Walk $walk, User $actor): Walk
    {
        DB::transaction(function () use ($walk, $actor): void {
            $locked = Walk::query()->lockForUpdate()->findOrFail($walk->id);
            $locked->setRelation('event', $locked->event()->lockForUpdate()->firstOrFail());
            Gate::forUser($actor)->authorize('unpublish', $locked);
            $locked->event->forceFill(['is_public' => false, 'published_at' => null])->save();
            $this->changeStatus->handle($locked->event, EventStatus::Draft, $actor);
        });

        return $walk->refresh()->load('event');
    }
}
