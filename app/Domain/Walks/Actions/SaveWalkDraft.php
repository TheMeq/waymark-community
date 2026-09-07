<?php

namespace App\Domain\Walks\Actions;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Walks\Models\Walk;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class SaveWalkDraft
{
    public function __construct(
        private GenerateWalkSlug $generateWalkSlug,
        private SaveWalkDetails $saveWalkDetails,
        private UpdateWalk $updateWalk,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, array $attributes): Walk
    {
        Gate::forUser($actor)->authorize('create', Walk::class);

        return DB::transaction(function () use ($actor, $attributes): Walk {
            $validated = Validator::make($attributes, [
                'title' => ['required', 'string', 'max:255'],
                'starts_at' => ['required', 'date'],
                'ends_at' => ['nullable', 'date', 'after:starts_at'],
                'meeting_location_name' => ['nullable', 'string', 'max:255'],
                'meeting_address' => ['nullable', 'string', 'max:5000'],
                'meeting_postcode' => ['nullable', 'string', 'max:32'],
                'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
                'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
                'what3words' => ['nullable', 'string', 'max:255'],
                'os_grid_reference' => ['nullable', 'string', 'max:255'],
            ])->validate();

            $event = Event::query()->create([
                ...Arr::only($validated, ['title', 'starts_at', 'ends_at']),
                'slug' => $this->generateWalkSlug->handle((string) $validated['title'], $validated['starts_at']),
                'type' => EventType::Walk,
                'status' => EventStatus::Draft,
                'is_public' => false,
                'published_at' => null,
                'organiser_id' => $actor->id,
            ]);

            return $this->saveWalkDetails->handle($event, [
                ...Arr::only($validated, [
                    'meeting_location_name',
                    'meeting_address',
                    'meeting_postcode',
                    'latitude',
                    'longitude',
                    'what3words',
                    'os_grid_reference',
                ]),
                'primary_leader_id' => $actor->id,
            ]);
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(Walk $draft, User $actor, array $attributes): Walk
    {
        return DB::transaction(function () use ($draft, $actor, $attributes): Walk {
            $lockedWalk = Walk::query()
                ->with(['coLeaders', 'tags'])
                ->lockForUpdate()
                ->findOrFail($draft->getKey());
            $lockedWalk->setRelation(
                'event',
                $lockedWalk->event()->lockForUpdate()->firstOrFail(),
            );

            Gate::forUser($actor)->authorize('update', $lockedWalk);

            if ($lockedWalk->event->status !== EventStatus::Draft) {
                throw ValidationException::withMessages([
                    'draft' => 'Only a draft walk can be saved from the Add Walk wizard.',
                ]);
            }

            $lockedWalk->event->forceFill([
                'is_public' => false,
                'published_at' => null,
            ])->save();

            return $this->updateWalk->handle(
                $lockedWalk,
                $actor,
                Arr::except($attributes, ['slug', 'status', 'is_public', 'published_at', 'organiser_id']),
            );
        });
    }
}
