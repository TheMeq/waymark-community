<?php

namespace App\Domain\Holidays\Actions;

use App\Domain\Holidays\Models\Holiday;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final readonly class UpdateHoliday
{
    public function __construct(private SaveHolidayDetails $saveHolidayDetails) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Holiday $holiday, User $user, array $attributes): Holiday
    {
        Gate::forUser($user)->authorize('update', $holiday);
        $event = $holiday->event;
        $common = Validator::make($attributes, [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:events,slug,'.$event->id],
            'summary' => ['nullable', 'string', 'max:65535'],
            'description' => ['nullable', 'string', 'max:65535'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
        ])->validate();

        return DB::transaction(function () use ($attributes, $common, $event): Holiday {
            $event->update(Arr::only($common, ['title', 'slug', 'summary', 'description', 'starts_at', 'ends_at']));

            return $this->saveHolidayDetails->handle($event, $attributes);
        });
    }
}
