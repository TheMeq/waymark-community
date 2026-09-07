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

final readonly class CreateWalk
{
    /** @param array<string, mixed> $attributes */
    public function __construct(
        private GenerateWalkSlug $generateWalkSlug,
        private SaveWalkDetails $saveWalkDetails,
        private SubmitWalkForPublication $submitWalkForPublication,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $organiser, array $attributes): Walk
    {
        Gate::forUser($organiser)->authorize('create', Walk::class);

        $walk = DB::transaction(function () use ($organiser, $attributes): Walk {
            $event = Event::query()->create([
                ...Arr::only($attributes, ['title', 'summary', 'description', 'starts_at', 'ends_at']),
                'slug' => $this->generateWalkSlug->handle((string) $attributes['title'], $attributes['starts_at']),
                'type' => EventType::Walk,
                'status' => EventStatus::Draft,
                'is_public' => false,
                'published_at' => null,
                'organiser_id' => $organiser->id,
            ]);

            return $this->saveWalkDetails->handle($event, $attributes);
        });

        return $this->submitWalkForPublication->handle($walk, $organiser);
    }
}
