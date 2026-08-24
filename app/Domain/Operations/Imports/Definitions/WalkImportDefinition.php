<?php

namespace App\Domain\Operations\Imports\Definitions;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Operations\Imports\Contracts\ImportDefinition;
use App\Domain\Walks\Models\Grade;
use App\Domain\Walks\Models\Walk;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Throwable;

final class WalkImportDefinition implements ImportDefinition
{
    public function key(): string
    {
        return 'walks';
    }

    public function label(): string
    {
        return 'Walks';
    }

    public function fields(): array
    {
        return [
            'title' => ['label' => 'Title', 'required' => true],
            'starts_at' => ['label' => 'Start date and time', 'required' => true],
            'leader_email' => ['label' => 'Leader email', 'required' => true],
            'ends_at' => ['label' => 'End date and time', 'required' => false],
            'summary' => ['label' => 'Summary', 'required' => false],
            'description' => ['label' => 'Description', 'required' => false],
            'grade' => ['label' => 'Grade', 'required' => false],
            'distance' => ['label' => 'Distance', 'required' => false],
            'ascent' => ['label' => 'Ascent', 'required' => false],
            'meeting_location_name' => ['label' => 'Meeting location', 'required' => false],
            'meeting_postcode' => ['label' => 'Meeting postcode', 'required' => false],
            'availability' => ['label' => 'Availability', 'required' => false],
        ];
    }

    public function assess(array $values): array
    {
        $messages = [];
        $title = trim((string) ($values['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 255) {
            $messages[] = 'Title is required and must be 255 characters or fewer.';
        }
        $startsAt = $this->date($values['starts_at'] ?? null);
        if ($startsAt === null) {
            $messages[] = 'Start must be a valid date and time.';
        }
        $endsAt = $this->date($values['ends_at'] ?? null);
        if (($values['ends_at'] ?? null) !== null && $endsAt === null) {
            $messages[] = 'End must be a valid date and time.';
        } elseif ($startsAt !== null && $endsAt !== null && $endsAt->lessThan($startsAt)) {
            $messages[] = 'End must not be before the start.';
        }

        $leaderEmail = mb_strtolower(trim((string) ($values['leader_email'] ?? '')));
        $leader = $leaderEmail === '' ? null : User::query()->whereRaw('LOWER(email) = ?', [$leaderEmail])->first();
        if (! $leader instanceof User || ! $leader->isEligibleWalkLeader()) {
            $messages[] = 'Leader email must match an existing walk leader.';
        }

        $gradeName = trim((string) ($values['grade'] ?? ''));
        $grade = $gradeName === '' ? null : Grade::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($gradeName)])->first();
        if ($gradeName !== '' && ! $grade instanceof Grade) {
            $messages[] = 'Grade must match an existing grade.';
        }

        $distance = $this->nonNegativeNumber($values['distance'] ?? null, 'Distance', $messages);
        $ascent = $this->nonNegativeNumber($values['ascent'] ?? null, 'Ascent', $messages);
        $normalized = [
            'title' => $title,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'leader_id' => $leader?->id,
            'grade_id' => $grade?->id,
            'summary' => $this->nullable($values['summary'] ?? null),
            'description' => $this->nullable($values['description'] ?? null),
            'distance' => $distance,
            'ascent' => $ascent,
            'meeting_location_name' => $this->nullable($values['meeting_location_name'] ?? null),
            'meeting_postcode' => $this->nullable($values['meeting_postcode'] ?? null),
            'availability' => $this->nullable($values['availability'] ?? null),
        ];
        $identity = $messages === []
            ? mb_strtolower($title).'|'.$startsAt?->format('Y-m-d H:i:s').'|'.$leader?->id
            : null;

        return compact('messages', 'normalized', 'identity');
    }

    public function duplicateExists(array $normalized): bool
    {
        if (! $normalized['starts_at'] instanceof CarbonImmutable || ! is_int($normalized['leader_id'])) {
            return false;
        }

        return Event::query()
            ->where('type', EventType::Walk)
            ->whereRaw('LOWER(title) = ?', [mb_strtolower((string) $normalized['title'])])
            ->where('starts_at', $normalized['starts_at'])
            ->whereHas('walk', fn (Builder $query): Builder => $query->where('primary_leader_id', $normalized['leader_id']))
            ->exists();
    }

    public function import(array $normalized): void
    {
        $event = Event::query()->create([
            'type' => EventType::Walk,
            'title' => $normalized['title'],
            'slug' => $this->uniqueSlug((string) $normalized['title']),
            'summary' => $normalized['summary'],
            'description' => $normalized['description'],
            'starts_at' => $normalized['starts_at'],
            'ends_at' => $normalized['ends_at'],
            'status' => EventStatus::Draft,
            'is_public' => false,
            'published_at' => null,
            'organiser_id' => $normalized['leader_id'],
        ]);
        Walk::query()->create([
            'event_id' => $event->id,
            'grade_id' => $normalized['grade_id'],
            'primary_leader_id' => $normalized['leader_id'],
            'distance' => $normalized['distance'],
            'ascent' => $normalized['ascent'],
            'meeting_location_name' => $normalized['meeting_location_name'],
            'meeting_postcode' => $normalized['meeting_postcode'],
            'availability' => $normalized['availability'],
        ]);
    }

    private function date(?string $value): ?CarbonImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /** @param list<string> $messages */
    private function nonNegativeNumber(?string $value, string $label, array &$messages): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        if (! is_numeric($value) || (float) $value < 0) {
            $messages[] = $label.' must be a non-negative number.';

            return null;
        }

        return $value;
    }

    private function nullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'walk';
        $slug = $base;
        $suffix = 2;
        while (Event::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
