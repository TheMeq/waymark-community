<?php

namespace App\Domain\Operations\Imports;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Events\Actions\GenerateEventSlug;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Operations\Queries\CurrentSiteProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

abstract class CsvImportSupport
{
    protected function commonFields(): array
    {
        return [
            'title' => ['label' => 'Title', 'required' => true],
            'starts_at' => ['label' => 'Start date and time', 'required' => true],
            ...$this->optionalFields(['ends_at', 'start_time_unknown', 'summary', 'description', 'import_enabled', 'review_notes', 'source_references', 'source_payload']),
        ];
    }

    protected function optionalFields(array $names): array
    {
        return array_combine($names, array_map(static fn (string $name): array => ['label' => Str::headline($name), 'required' => false], $names));
    }

    protected function common(array $values, EventType $type, bool $requiresActor = true): array
    {
        $messages = [];
        $title = Str::squish((string) ($values['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 255) {
            $messages[] = 'Title is required and must be 255 characters or fewer.';
        }
        [$startsAt, $dateOnly] = $this->date($values['starts_at'] ?? null);
        if ($startsAt === null) {
            $messages[] = 'Start must be a valid date and time, or a date-only value.';
        }
        [$endsAt] = $this->date($values['ends_at'] ?? null);
        if ($this->text($values['ends_at'] ?? null) !== null && $endsAt === null) {
            $messages[] = 'End must be a valid date or date and time.';
        } elseif ($startsAt !== null && $endsAt !== null && $endsAt->lessThan($startsAt)) {
            $messages[] = 'End must not be before the start.';
        }
        $flag = $this->boolean($values['start_time_unknown'] ?? null, 'Start time unknown', $messages);
        $unknown = $dateOnly || $flag === true;
        if ($unknown && $startsAt !== null && $startsAt->format('H:i:s') !== '00:00:00') {
            $messages[] = 'An unknown start time must use a date-only value or midnight placeholder.';
        }
        foreach (['summary', 'description', 'review_notes'] as $field) {
            if (mb_strlen((string) ($values[$field] ?? '')) > 65535) {
                $messages[] = Str::headline($field).' must contain no more than 65,535 characters.';
            }
        }
        [$references, $referenceError] = $this->jsonList($values['source_references'] ?? null, 65535);
        [$payload, $payloadError] = $this->jsonList($values['source_payload'] ?? null, 1_048_576);
        if ($referenceError) {
            $messages[] = 'Source references must be a JSON list.';
        }
        if ($payloadError) {
            $messages[] = 'Source payload must be a JSON list no larger than 1 MB.';
        }
        $actor = auth()->user();
        $capability = match ($type) {
            EventType::Walk => ModuleCapability::ManageAllWalks,
            EventType::Social => ModuleCapability::ManageSocials,
            EventType::Holiday => ModuleCapability::ManageHolidays,
        };
        $organiserId = $actor instanceof User && $actor->hasVerifiedEmail()
            && $actor->hasCapability(ModuleCapability::ManageAccounts) && $actor->hasCapability($capability) ? $actor->id : null;
        if ($requiresActor && $organiserId === null) {
            $messages[] = 'An authorised administrator is required to import this record.';
        }
        $reviewNotes = $this->text($values['review_notes'] ?? null);
        $normalized = [
            'title' => $title, 'starts_at' => $startsAt, 'ends_at' => $endsAt, 'start_time_unknown' => $unknown,
            'summary' => $this->text($values['summary'] ?? null), 'description' => $this->text($values['description'] ?? null),
            'organiser_id' => $organiserId,
            'import_source' => ['references' => $references, 'payload' => $payload, 'review_notes' => $reviewNotes],
        ];
        $identity = $startsAt !== null && $title !== '' ? mb_strtolower($title).'|'.$startsAt->format('Y-m-d H:i:s').'|'.$type->value : null;

        return ['messages' => $messages, 'normalized' => $normalized, 'identity' => $identity, 'warnings' => $reviewNotes === null ? [] : [$reviewNotes]];
    }

    protected function duplicate(array $normalized, EventType $type): bool
    {
        $start = $normalized['starts_at'] ?? null;
        if (! $start instanceof CarbonImmutable) {
            return false;
        }

        return Event::query()->where('type', $type)->whereDate('starts_at', $start->format('Y-m-d'))
            ->get(['title', 'starts_at', 'start_time_unknown'])->contains(static fn (Event $event): bool => mb_strtolower(Str::squish($event->title)) === mb_strtolower($normalized['title'])
                && ($normalized['start_time_unknown'] || $event->start_time_unknown || $event->starts_at->equalTo($start)));
    }

    protected function event(array $data, EventType $type): Event
    {
        return Event::query()->create([
            ...Arr::only($data, ['title', 'summary', 'description', 'starts_at', 'ends_at', 'start_time_unknown', 'import_source', 'organiser_id']),
            'type' => $type,
            'slug' => app(GenerateEventSlug::class)->handle($data['title'], $data['starts_at'], $type),
            'status' => EventStatus::Draft, 'is_public' => false, 'published_at' => null,
        ]);
    }

    protected function text(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    protected function boolean(mixed $value, string $name, array &$messages): ?bool
    {
        $value = mb_strtolower($this->text($value) ?? '');
        if (in_array($value, ['', '1', 'true', 'yes', 'y', '0', 'false', 'no', 'n'], true)) {
            return $value === '' ? null : in_array($value, ['1', 'true', 'yes', 'y'], true);
        }
        $messages[] = $name.' must be yes or no.';

        return null;
    }

    private function date(mixed $value): array
    {
        $value = $this->text($value);
        if ($value === null) {
            return [null, false];
        }
        $timezone = app(CurrentSiteProfile::class)->get()->timezone;
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:iP', 'Y-m-d'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $value, $timezone);
                if ($date !== false && $date->format($format) === $value) {
                    return [$date, $format === 'Y-m-d'];
                }
            } catch (Throwable) {
                // Continue through the supported, unambiguous date formats.
            }
        }

        return [null, false];
    }

    private function jsonList(mixed $value, int $maximum): array
    {
        $value = $this->text($value);
        if ($value === null) {
            return [[], false];
        }
        if (strlen($value) <= $maximum) {
            try {
                $decoded = json_decode($value, true, 128, JSON_THROW_ON_ERROR);
                if (is_array($decoded) && array_is_list($decoded)) {
                    return [$decoded, false];
                }
            } catch (Throwable) {
                // Report malformed JSON through the normal row validation.
            }
        }

        return [[], true];
    }
}
