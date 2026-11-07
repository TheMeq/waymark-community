<?php

namespace App\Domain\Holidays\Actions;

use App\Domain\Events\Validation\ValidStartTimePrecision;
use App\Domain\Holidays\Models\Holiday;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class UpdateHoliday
{
    public function __construct(private SaveHolidayDetails $saveHolidayDetails) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Holiday $holiday, User $user, array $attributes): Holiday
    {
        Gate::forUser($user)->authorize('update', $holiday);
        $event = $holiday->event;
        $attributes = ['start_time_unknown' => $event->start_time_unknown, ...$attributes];
        $common = Validator::make($attributes, [
            'organiser_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'organiser_name' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:events,slug,'.$event->id],
            'summary' => ['nullable', 'string', 'max:65535'],
            'description' => ['nullable', 'string', 'max:65535'],
            'starts_at' => ['required', 'date'],
            'start_time_unknown' => ['sometimes', 'boolean', new ValidStartTimePrecision($attributes['starts_at'] ?? null)],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
        ])->validate();

        $startsAt = CarbonImmutable::parse($common['starts_at']);
        $endsAt = CarbonImmutable::parse($common['ends_at']);
        $hasChildOutsideDates = $event->children()->get()->contains(
            fn ($child): bool => $child->starts_at->lessThan($startsAt)
                || ($child->ends_at ?? $child->starts_at)->greaterThan($endsAt),
        );
        if ($hasChildOutsideDates) {
            throw ValidationException::withMessages([
                'dates' => 'Holiday dates must continue to contain every attached itinerary event.',
            ]);
        }

        if (array_key_exists('organiser_name', $common)) {
            $common['organiser_name'] = trim($common['organiser_name'] ?? '') ?: null;
        }

        return DB::transaction(function () use ($attributes, $common, $event): Holiday {
            $event->update(Arr::only($common, ['title', 'slug', 'summary', 'description', 'starts_at', 'ends_at', 'start_time_unknown', 'organiser_id', 'organiser_name']));

            return $this->saveHolidayDetails->handle($event, $attributes);
        });
    }
}
