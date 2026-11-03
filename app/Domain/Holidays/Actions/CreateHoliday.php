<?php

namespace App\Domain\Holidays\Actions;

use App\Domain\Events\Actions\GenerateEventSlug;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Holidays\Models\Holiday;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final readonly class CreateHoliday
{
    public function __construct(
        private SaveHolidayDetails $saveHolidayDetails,
        private GenerateEventSlug $generateSlug,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $organiser, array $attributes): Holiday
    {
        Gate::forUser($organiser)->authorize('create', Holiday::class);
        if (is_string($attributes['slug'] ?? null) && trim($attributes['slug']) === '') {
            $attributes['slug'] = null;
        }

        $common = Validator::make($attributes, [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:events,slug'],
            'summary' => ['nullable', 'string', 'max:65535'],
            'description' => ['nullable', 'string', 'max:65535'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
        ])->validate();

        return DB::transaction(function () use ($organiser, $attributes, $common): Holiday {
            $event = Event::query()->create([
                ...Arr::only($common, ['title', 'slug', 'summary', 'description', 'starts_at', 'ends_at']),
                'slug' => $common['slug'] ?? $this->generateSlug->handle($common['title'], $common['starts_at'], EventType::Holiday),
                'type' => EventType::Holiday,
                'status' => EventStatus::Draft,
                'is_public' => false,
                'published_at' => null,
                'organiser_id' => $organiser->id,
            ]);

            return $this->saveHolidayDetails->handle($event, $attributes);
        });
    }
}
