<?php

namespace App\Domain\Events\Actions;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\RecurringSeries;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class CreateRecurringSeries
{
    public function handle(Event $source, string $frequency, int $interval, int $occurrenceCount): RecurringSeries
    {
        Validator::make(compact('frequency', 'interval', 'occurrenceCount'), [
            'frequency' => ['required', Rule::in(['weeks', 'months'])],
            'interval' => ['required', 'integer', 'min:1', 'max:52'],
            'occurrenceCount' => ['required', 'integer', 'min:2', 'max:100'],
        ])->validate();

        if ($source->recurring_series_id !== null || RecurringSeries::query()->where('source_event_id', $source->id)->exists()) {
            throw ValidationException::withMessages(['source' => 'This event already belongs to a recurring series.']);
        }

        $source->loadMissing(['walk.coLeaders', 'walk.tags', 'social', 'holiday']);

        return DB::transaction(function () use ($source, $frequency, $interval, $occurrenceCount): RecurringSeries {
            $series = RecurringSeries::query()->create([
                'source_event_id' => $source->id,
                'frequency' => $frequency,
                'interval' => $interval,
                'occurrence_count' => $occurrenceCount,
            ]);
            $source->update(['recurring_series_id' => $series->id, 'occurrence_number' => 1]);

            for ($number = 2; $number <= $occurrenceCount; $number++) {
                $occurrence = $this->cloneEvent($source, $series, $number);
                $this->cloneExtension($source, $occurrence);
            }

            return $series->refresh();
        });
    }

    private function cloneEvent(Event $source, RecurringSeries $series, int $number): Event
    {
        $offset = ($number - 1) * $series->interval;
        $start = $this->shift($source->starts_at, $series->frequency, $offset);
        $end = $source->ends_at === null ? null : $start->copy()->addSeconds($source->starts_at->diffInSeconds($source->ends_at));
        $occurrence = $source->replicate(['slug', 'parent_event_id', 'recurring_series_id', 'occurrence_number']);
        $occurrence->slug = $this->uniqueSlug($source->slug.'-'.$start->format('Y-m-d'));
        $occurrence->starts_at = $start;
        $occurrence->ends_at = $end;
        $occurrence->parent_event_id = null;
        $occurrence->recurring_series_id = $series->id;
        $occurrence->occurrence_number = $number;
        $occurrence->save();

        return $occurrence;
    }

    private function cloneExtension(Event $source, Event $occurrence): void
    {
        if ($source->social !== null) {
            $copy = $source->social->replicate(['event_id']);
            $copy->event_id = $occurrence->id;
            $copy->save();
        }
        if ($source->holiday !== null) {
            $copy = $source->holiday->replicate(['event_id']);
            $copy->event_id = $occurrence->id;
            $copy->save();
        }
        if ($source->walk !== null) {
            $copy = $source->walk->replicate(['event_id']);
            $copy->event_id = $occurrence->id;
            $copy->save();
            $copy->coLeaders()->sync($source->walk->coLeaders->modelKeys());
            $copy->tags()->sync($source->walk->tags->modelKeys());
        }
    }

    private function shift(CarbonInterface $date, string $frequency, int $offset): CarbonInterface
    {
        return $frequency === 'weeks'
            ? $date->copy()->addWeeks($offset)
            : $date->copy()->addMonthsNoOverflow($offset);
    }

    private function uniqueSlug(string $candidate): string
    {
        $slug = $candidate;
        for ($suffix = 2; Event::query()->where('slug', $slug)->exists(); $suffix++) {
            $slug = $candidate.'-'.$suffix;
        }

        return $slug;
    }
}
