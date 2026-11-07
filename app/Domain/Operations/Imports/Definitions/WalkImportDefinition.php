<?php

namespace App\Domain\Operations\Imports\Definitions;

use App\Domain\Events\Enums\EventType;
use App\Domain\Operations\Imports\Contracts\ImportDefinition;
use App\Domain\Operations\Imports\CsvImportSupport;
use App\Domain\Operations\Queries\CurrentSiteProfile;
use App\Domain\Walks\Actions\SaveWalkDetails;
use App\Domain\Walks\Data\WalkDetailsData;
use App\Domain\Walks\Models\Grade;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

final class WalkImportDefinition extends CsvImportSupport implements ImportDefinition
{
    private const TEXT_FIELDS = ['meeting_location_name', 'meeting_address', 'meeting_postcode', 'os_grid_reference', 'what3words', 'directions', 'parking_notes', 'public_transport_notes', 'toilet_information', 'cafe_pub_information', 'dog_guidance', 'accessibility_notes', 'private_organiser_notes', 'availability', 'estimated_duration_minutes', 'capacity'];

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
        return [...$this->commonFields(), ...$this->optionalFields([
            'leader_name', 'leader_email', 'grade', 'distance', 'distance_unit', 'ascent', 'ascent_unit',
            'is_public_transport_friendly', ...self::TEXT_FIELDS,
        ])];
    }

    public function assess(array $values): array
    {
        $assessment = $this->common($values, EventType::Walk, false);
        $email = mb_strtolower($this->text($values['leader_email'] ?? null) ?? '');
        $leader = $email === '' ? null : User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($email !== '' && (! $leader instanceof User || ! $leader->isEligibleWalkLeader())) {
            $assessment['messages'][] = 'Leader email must match an existing walk leader.';
        }
        if ($leader !== null && $leader->isEligibleWalkLeader()) {
            $assessment['normalized']['organiser_id'] = $leader->id;
        }
        if ($assessment['normalized']['organiser_id'] === null) {
            $assessment['messages'][] = 'An authorised administrator is required for a name-only or unconfirmed leader.';
        }
        $gradeName = $this->text($values['grade'] ?? null);
        $grade = $gradeName === null ? null : Grade::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($gradeName)])->first();
        if ($gradeName !== null && $grade === null) {
            $assessment['messages'][] = 'Grade must match an existing grade.';
        }
        $details = ['primary_leader_id' => $leader?->id, 'primary_leader_name' => $this->text($values['leader_name'] ?? null), 'grade_id' => $grade?->id];
        foreach (['distance', 'ascent'] as $measurement) {
            $value = $this->measurement($values[$measurement] ?? null, $values[$measurement.'_unit'] ?? null, $measurement);
            if ($value === false) {
                $assessment['messages'][] = ucfirst($measurement).' must be a non-negative number or fraction with a supported unit.';
            }
            $details[$measurement] = $value === false ? null : $value;
        }
        foreach (self::TEXT_FIELDS as $field) {
            $details[$field] = $this->text($values[$field] ?? null);
        }
        $details['is_public_transport_friendly'] = $this->boolean($values['is_public_transport_friendly'] ?? null, 'Public transport friendly', $assessment['messages']) ?? false;
        try {
            WalkDetailsData::from($details);
        } catch (ValidationException $exception) {
            $assessment['messages'] = [...$assessment['messages'], ...$exception->validator->errors()->all()];
        }
        $assessment['normalized'] += $details;

        return $assessment;
    }

    public function duplicateExists(array $normalized): bool
    {
        return $this->duplicate($normalized, EventType::Walk);
    }

    public function import(array $normalized): void
    {
        $event = $this->event($normalized, EventType::Walk);
        app(SaveWalkDetails::class)->handle($event, Arr::only($normalized, [
            'primary_leader_id', 'primary_leader_name', 'grade_id', 'distance', 'ascent', 'is_public_transport_friendly', ...self::TEXT_FIELDS,
        ]));
    }

    private function measurement(mixed $value, mixed $unit, string $kind): string|false|null
    {
        $value = $this->text($value);
        if ($value === null) {
            return null;
        }
        $fractions = ['½' => .5, '¼' => .25, '¾' => .75, '⅓' => 1 / 3, '⅔' => 2 / 3, '⅛' => .125, '⅜' => .375, '⅝' => .625, '⅞' => .875];
        if (preg_match('/^(\d*)\s*([½¼¾⅓⅔⅛⅜⅝⅞])$/u', $value, $match)) {
            $value = (string) ((int) $match[1] + $fractions[$match[2]]);
        } elseif (preg_match('~^(?:(\d+)\s+)?(\d+)/(\d+)$~', $value, $match) && (int) $match[3] > 0) {
            $value = (string) ((int) $match[1] + (int) $match[2] / (int) $match[3]);
        }
        if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0) {
            return false;
        }
        $profile = app(CurrentSiteProfile::class)->get();
        $configuredUnit = $kind === 'distance' ? $profile->distance_unit : $profile->ascent_unit;
        $unit = mb_strtolower($this->text($unit) ?? $configuredUnit);
        if (! in_array($unit, $kind === 'distance' ? ['miles', 'kilometres'] : ['feet', 'metres'], true)) {
            return false;
        }
        $number = (float) $value;
        if ($unit !== $configuredUnit) {
            $factor = $kind === 'distance' ? 1.609344 : .3048;
            $number = in_array($unit, ['miles', 'feet'], true) ? $number * $factor : $number / $factor;
        }

        return number_format($number, 2, '.', '');
    }
}
