<?php

namespace App\Domain\Walks\Actions;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Events\Actions\ChangeEventStatus;
use App\Domain\Events\Actions\PublishEvent;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Walks\Models\Walk;
use App\Domain\Walks\Models\WalkFieldSettings;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class SubmitWalkForPublication
{
    public function __construct(
        private ChangeEventStatus $changeEventStatus,
        private PublishEvent $publishEvent,
    ) {}

    public function handle(Walk $walk, User $actor): Walk
    {
        Gate::forUser($actor)->authorize('publish', $walk);

        $walk->loadMissing('event');

        if ($walk->primary_leader_id === null && blank($walk->primary_leader_name)) {
            throw ValidationException::withMessages([
                'primary_leader' => 'Provide a primary leader name or link an eligible account before publication.',
            ]);
        }

        if ($walk->primary_leader_id === null && ! $actor->hasCapability(ModuleCapability::ManageAllWalks)) {
            throw ValidationException::withMessages([
                'primary_leader_id' => 'Only administrators can publish a walk with a name-only primary leader.',
            ]);
        }

        $canPublishDirectly = $actor->hasCapability(ModuleCapability::ManageAllWalks)
            || WalkFieldSettings::current()->leaders_can_publish_directly;

        if ($canPublishDirectly) {
            $this->publishEvent->handle($walk->event, $actor);

            return $walk->refresh()->load('event');
        }

        $walk->event->is_public = false;
        $walk->event->published_at = null;
        $walk->event->save();
        $this->changeEventStatus->handle($walk->event, EventStatus::PendingApproval, $actor);

        return $walk->refresh()->load('event');
    }
}
