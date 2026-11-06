<?php

namespace App\Domain\Operations\Imports;

use App\Domain\Accounts\Queries\EligibleWalkLeadersQuery;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Walks\Data\WalkDetailsData;
use App\Domain\Walks\Models\Grade;
use Carbon\CarbonImmutable;
use DateInterval;
use DateTimeInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class NormaliseWalkProgrammeRow
{
    /** @param array<string, mixed> $values
     * @param  array<string, mixed>  $leaderMapping
     * @return array{status: string, messages: list<string>, data: array<string, string|null>, normalized: array<string, mixed>}
     */
    public function assess(array $values, SiteProfile $profile, array $leaderMapping): array
    {
        $sourceTitle = $this->text($values['title'] ?? null) ?? '';
        $titleParts = preg_split('/\R/u', $sourceTitle, 2);
        $title = Str::squish($titleParts[0]);
        $leaderName = $this->text($values['walk leader name'] ?? null);
        $date = $this->date($values['date'] ?? null, $profile->timezone);
        $time = $this->time($values['start time'] ?? null);
        $startsAt = $date !== null && $time !== null ? $date->setTimeFromTimeString($time) : null;
        $data = ['title' => $title, 'starts_at' => $startsAt?->format('Y-m-d H:i'), 'leader_name' => $leaderName, 'grade' => $this->text($values['difficulty'] ?? null)];
        $messages = [];
        $normalized = [];
        $status = 'excluded';
        if ($this->text($values['date'] ?? null) === null) {
            $messages[] = 'No date: unscheduled idea excluded.';

            return compact('status', 'messages', 'data', 'normalized');
        }
        if ($date !== null && $date->lessThan(CarbonImmutable::today($profile->timezone))) {
            $messages[] = 'Past programme entry excluded.';

            return compact('status', 'messages', 'data', 'normalized');
        }
        if ($data['grade'] === null) {
            $messages[] = 'No walking grade: social, holiday or unclassified entry excluded.';

            return compact('status', 'messages', 'data', 'normalized');
        }
        $status = 'valid';
        if ($date === null) {
            $messages[] = 'A valid programme date is required.';
        }
        if ($time === null) {
            $messages[] = 'A confirmed start time is required.';
        }
        if ($title === '' || mb_strlen($title) > 255) {
            $messages[] = 'The walk title must contain 1–255 characters.';
        }
        $grade = Grade::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($data['grade'])])->first();
        if ($grade === null) {
            $messages[] = 'The walking grade must match a configured Waymark grade.';
        }
        $linkedId = $leaderMapping[hash('sha256', $leaderName ?? '')] ?? null;
        if ($linkedId === '') {
            $linkedId = null;
        }
        if ($linkedId !== null && (! ctype_digit((string) $linkedId) || ! app(EligibleWalkLeadersQuery::class)->contains((int) $linkedId))) {
            $messages[] = 'Choose an active, verified account with permission to manage walks.';
            $linkedId = null;
        }
        $distance = $this->measurement($values, 'dist miles', 'distance km', $profile->distance_unit === 'miles', 1.609344, 'Distance', $messages);
        $ascent = $this->measurement($values, 'ascent feet', 'ascent metres', $profile->ascent_unit === 'feet', 0.3048, 'Ascent', $messages);
        $combinedLocation = $this->text($values['google satnav & what3words'] ?? null);
        $what3words = null;
        if ($combinedLocation !== null && preg_match('~///([\p{L}\p{N}-]+\.[\p{L}\p{N}-]+\.[\p{L}\p{N}-]+)~u', $combinedLocation, $match)) {
            $what3words = $match[1];
            $combinedLocation = trim(str_replace($match[0], '', $combinedLocation));
        }
        $location = $this->text($values['starting location'] ?? null) ?? $this->text($combinedLocation);
        $finishValue = $values['est finish time (not guaranteed)'] ?? null;
        $finish = $this->time($finishValue);
        $endsAt = $date !== null && $finish !== null ? $date->setTimeFromTimeString($finish) : null;
        if ($this->text($finishValue) !== null && ($finish === null || ($startsAt !== null && $endsAt?->lessThanOrEqualTo($startsAt)))) {
            $messages[] = 'Estimated finish must be a valid time after the start.';
        }
        $description = implode("\n\n", array_filter([$titleParts[1] ?? null, $this->text($values['description'] ?? null), $this->text($values['additional details'] ?? null)]));
        $transport = $this->flag($values['public transport accessible'] ?? null) === true || filled($values['public trans'] ?? null);
        $details = [
            'primary_leader_id' => $linkedId === null ? null : (int) $linkedId,
            'primary_leader_name' => $leaderName,
            'grade_id' => $grade?->id,
            'distance' => $distance,
            'ascent' => $ascent,
            'meeting_location_name' => $location === null ? null : Str::limit($location, 255, ''),
            'meeting_address' => $location !== null && mb_strlen($location) > 255 ? $location : null,
            'meeting_postcode' => $this->text($values['starting postcode'] ?? null),
            'os_grid_reference' => $this->text($values['starting gridref'] ?? null),
            'what3words' => $what3words,
            'is_public_transport_friendly' => $transport,
            'public_transport_notes' => $transport ? 'Public transport access indicated in the programme; confirm services and timings.' : null,
            'dog_guidance' => $this->flagNote($values['dog friendly'] ?? null, 'Dogs welcome.', 'Dogs not permitted.'),
            'parking_notes' => $this->flagNote($values['car parking available'] ?? null, 'Car parking available.', 'No car parking indicated.'),
            'toilet_information' => $this->flagNote($values['toilets available'] ?? null, 'Toilets available.', 'No toilets indicated.'),
            'cafe_pub_information' => $this->flagNote($values['refreshments available'] ?? null, 'Refreshments available.', 'No refreshments indicated.'),
            'accessibility_notes' => $this->accessibilityNotes($values),
        ];
        try {
            WalkDetailsData::from($details);
        } catch (ValidationException $exception) {
            $messages = [...$messages, ...$exception->validator->errors()->all()];
        }
        if ($messages !== []) {
            $status = 'invalid';
        }
        if ($leaderName === null && $linkedId === null) {
            $messages[] = 'Leader still to be confirmed; an administrator must complete this draft.';
        } elseif ($linkedId === null) {
            $messages[] = 'Name-only leader; managed by administrators until an account is linked.';
        }
        if ($location === null) {
            $messages[] = 'Complete the starting location before publication.';
        }
        if ($description === '') {
            $messages[] = 'Complete the walk description before publication.';
        }
        if ($distance === null) {
            $messages[] = 'Confirm the walk distance before publication.';
        }
        $sourceNotes = [];
        foreach ($values as $key => $value) {
            $text = $this->text($value);
            if ($text !== null && ! in_array($key, ['title', 'description', 'additional details', 'website link'], true)) {
                $sourceNotes[] = ucfirst($key).': '.$text;
            }
        }
        $details['private_organiser_notes'] = Str::limit("Imported programme: confirm details before publication.\n".implode("\n", $sourceNotes), 10000, '');
        $normalized = [...$details, 'title' => $title, 'starts_at' => $startsAt, 'ends_at' => $endsAt, 'description' => $description === '' ? null : $description];
        $data['distance'] = $distance === null ? null : $distance.' '.$profile->distance_unit;
        $data['ascent'] = $ascent === null ? null : $ascent.' '.$profile->ascent_unit;
        $data['location'] = $location;
        $data['linked_account'] = $linkedId === null ? null : (string) $linkedId;

        return compact('status', 'messages', 'data', 'normalized');
    }

    private function date(mixed $value, string $timezone): ?CarbonImmutable
    {
        $value = $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $this->text($value);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $value ?? '', $timezone);
                if ($date !== false && $date->format($format) === $value) {
                    return $date;
                }
            } catch (Throwable) {
                // Try the next explicitly supported date format.
            }
        }

        return null;
    }

    private function time(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i:s');
        }
        if ($value instanceof DateInterval) {
            return $value->days === false && $value->d === 0 ? sprintf('%02d:%02d:%02d', $value->h, $value->i, $value->s) : null;
        }
        if (is_numeric($value) && (float) $value >= 0 && (float) $value < 1) {
            return gmdate('H:i:s', min(86399, (int) round((float) $value * 86400)));
        }
        $text = trim((string) ($value ?? ''));

        return preg_match('/^(?:[01]?\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/D', $text) ? $text : null;
    }

    private function measurement(array $values, string $imperial, string $metric, bool $useImperial, float $factor, string $label, array &$messages): ?string
    {
        $source = filled($values[$imperial] ?? null) ? $imperial : $metric;
        $value = $this->text($values[$source] ?? null);
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
            $messages[] = $label.' must be a non-negative number or fraction.';

            return null;
        }
        $number = (float) $value;
        if ($source === $imperial && ! $useImperial) {
            $number *= $factor;
        } elseif ($source === $metric && $useImperial) {
            $number /= $factor;
        }

        return number_format($number, 2, '.', '');
    }

    private function text(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if ($value instanceof DateInterval) {
            return $this->time($value);
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function flag(mixed $value): ?bool
    {
        return match (mb_strtolower($this->text($value) ?? '')) {
            '1', 'true', 'yes', 'y' => true,
            '0', 'false', 'no', 'n' => false,
            default => null,
        };
    }

    private function flagNote(mixed $value, string $yes, string $no): ?string
    {
        return match ($this->flag($value)) {
            true => $yes, false => $no, null => null
        };
    }

    private function accessibilityNotes(array $values): ?string
    {
        $notes = [];
        if ($this->flag($values['no stiles'] ?? null) === true) {
            $notes[] = 'No stiles indicated in the programme.';
        }
        if ($this->flag($values['wheelchair accessible'] ?? null) === true) {
            $notes[] = 'Wheelchair access indicated in the programme; confirm route suitability.';
        }

        return $notes === [] ? null : implode(' ', $notes);
    }
}
