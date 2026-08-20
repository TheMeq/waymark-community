<?php

namespace App\Domain\Events\Actions;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventUpdate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final readonly class AddEventUpdate
{
    public function __construct(private ChangeEventStatus $changeEventStatus) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Event $event, User $actor, array $attributes): EventUpdate
    {
        $event->loadMissing('walk');
        Gate::forUser($actor)->authorize('create', [EventUpdate::class, $event]);

        $validated = Validator::make($this->normalise($attributes), [
            'message' => ['required', 'string', 'max:5000', 'not_regex:/<[^>]*>/'],
            'is_significant' => ['sometimes', 'boolean'],
        ])->validate();

        return DB::transaction(function () use ($event, $actor, $validated): EventUpdate {
            $update = $event->updates()->create([
                'author_id' => $actor->id,
                'message' => $validated['message'],
                'is_significant' => $validated['is_significant'] ?? false,
            ]);

            if ($update->is_significant && $this->canMarkAsChanged($event)) {
                $this->changeEventStatus->handle($event, EventStatus::Changed, $actor);
            }

            return $update;
        });
    }

    private function canMarkAsChanged(Event $event): bool
    {
        return ! $event->isPast()
            && $event->is_public
            && $event->published_at !== null
            && $event->published_at->lessThanOrEqualTo(now())
            && in_array($event->status, [EventStatus::Published, EventStatus::Changed], true);
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function normalise(array $attributes): array
    {
        if (is_string($attributes['message'] ?? null)) {
            $attributes['message'] = trim($attributes['message']);
        }

        return $attributes;
    }
}
