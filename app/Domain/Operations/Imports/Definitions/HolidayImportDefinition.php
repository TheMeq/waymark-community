<?php

namespace App\Domain\Operations\Imports\Definitions;

use App\Domain\Events\Enums\EventType;
use App\Domain\Holidays\Actions\SaveHolidayDetails;
use App\Domain\Holidays\Data\HolidayDetailsData;
use App\Domain\Operations\Imports\Contracts\ImportDefinition;
use App\Domain\Operations\Imports\CsvImportSupport;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

final class HolidayImportDefinition extends CsvImportSupport implements ImportDefinition
{
    private const FIELDS = ['destination', 'accommodation', 'pricing_type', 'price_amount', 'currency', 'pricing_notes', 'booking_status', 'booking_instructions', 'booking_contact', 'travel_details', 'itinerary_notes', 'availability', 'booking_deadline', 'booking_url', 'capacity', 'deposit_amount'];

    public function key(): string
    {
        return 'holidays';
    }

    public function label(): string
    {
        return 'Holidays';
    }

    public function fields(): array
    {
        return [...$this->commonFields(), ...$this->optionalFields(self::FIELDS)];
    }

    public function assess(array $values): array
    {
        $assessment = $this->common($values, EventType::Holiday);
        $details = [];
        foreach (self::FIELDS as $field) {
            $details[$field] = $this->text($values[$field] ?? null);
        }
        try {
            HolidayDetailsData::validate($details);
        } catch (ValidationException $exception) {
            $assessment['messages'] = [...$assessment['messages'], ...$exception->validator->errors()->all()];
        }
        $assessment['normalized'] += $details;

        return $assessment;
    }

    public function duplicateExists(array $normalized): bool
    {
        return $this->duplicate($normalized, EventType::Holiday);
    }

    public function import(array $normalized): void
    {
        $event = $this->event($normalized, EventType::Holiday);
        app(SaveHolidayDetails::class)->handle($event, Arr::only($normalized, self::FIELDS));
    }
}
