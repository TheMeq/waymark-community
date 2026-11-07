<?php

namespace App\Domain\Operations\Imports\Definitions;

use App\Domain\Events\Enums\EventType;
use App\Domain\Operations\Imports\Contracts\ImportDefinition;
use App\Domain\Operations\Imports\CsvImportSupport;
use App\Domain\Socials\Actions\SaveSocialDetails;
use App\Domain\Socials\Data\SocialDetailsData;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

final class EventImportDefinition extends CsvImportSupport implements ImportDefinition
{
    private const FIELDS = ['venue_name', 'venue_address', 'cost', 'booking_status', 'booking_instructions', 'booking_url', 'contact_name', 'contact_details', 'transport_notes', 'accessibility_notes', 'availability', 'capacity'];

    public function key(): string
    {
        return 'events';
    }

    public function label(): string
    {
        return 'Events';
    }

    public function fields(): array
    {
        return [...$this->commonFields(), ...$this->optionalFields(self::FIELDS)];
    }

    public function assess(array $values): array
    {
        $assessment = $this->common($values, EventType::Social);
        $details = [];
        foreach (self::FIELDS as $field) {
            $details[$field] = $this->text($values[$field] ?? null);
        }
        try {
            SocialDetailsData::from($details);
        } catch (ValidationException $exception) {
            $assessment['messages'] = [...$assessment['messages'], ...$exception->validator->errors()->all()];
        }
        $assessment['normalized'] += $details;

        return $assessment;
    }

    public function duplicateExists(array $normalized): bool
    {
        return $this->duplicate($normalized, EventType::Social);
    }

    public function import(array $normalized): void
    {
        $event = $this->event($normalized, EventType::Social);
        app(SaveSocialDetails::class)->handle($event, Arr::only($normalized, self::FIELDS));
    }
}
