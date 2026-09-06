<?php

namespace App\Domain\Walks\Actions;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Walks\Models\Walk;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final readonly class CreateWalk
{
    /** @param array<string, mixed> $attributes */
    public function __construct(
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
                'slug' => $this->uniqueSlug((string) $attributes['title'], $attributes['starts_at']),
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

    private function uniqueSlug(string $title, mixed $startsAt): string
    {
        $date = $startsAt instanceof CarbonInterface
            ? $startsAt
            : CarbonImmutable::parse((string) $startsAt);
        $dateSuffix = '-'.$date->format('Y-m-d');
        $titleSlug = Str::slug($title) ?: 'walk';
        $base = Str::limit($titleSlug, 255 - strlen($dateSuffix), '').$dateSuffix;
        $slug = $base;

        for ($suffix = 2; Event::query()->where('slug', $slug)->exists(); $suffix++) {
            $suffixText = '-'.$suffix;
            $slug = Str::limit($base, 255 - strlen($suffixText), '').$suffixText;
        }

        return $slug;
    }
}
