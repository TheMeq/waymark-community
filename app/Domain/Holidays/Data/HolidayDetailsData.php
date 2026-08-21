<?php

namespace App\Domain\Holidays\Data;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class HolidayDetailsData
{
    /** @param array<string, mixed> $attributes */
    public static function validate(array $attributes): array
    {
        $validated = Validator::make($attributes, [
            'destination' => ['nullable', 'string', 'max:255'],
            'accommodation' => ['nullable', 'string', 'max:5000'],
            'pricing_type' => ['nullable', Rule::in(['free', 'tbc', 'fixed', 'from'])],
            'price_amount' => ['nullable', 'numeric', 'min:0', 'required_if:pricing_type,fixed,from'],
            'currency' => ['nullable', 'string', 'size:3', 'required_if:pricing_type,fixed,from'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'pricing_notes' => ['nullable', 'string', 'max:5000'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'availability' => ['nullable', 'string', 'max:255'],
            'booking_deadline' => ['nullable', 'date'],
            'booking_status' => ['nullable', 'string', 'max:255'],
            'booking_instructions' => ['nullable', 'string', 'max:5000'],
            'booking_url' => ['nullable', 'url:http,https', 'max:2048'],
            'booking_contact' => ['nullable', 'string', 'max:5000'],
            'travel_details' => ['nullable', 'string', 'max:10000'],
            'itinerary_notes' => ['nullable', 'string', 'max:65535'],
            'featured_image_path' => ['nullable', 'string', 'max:2048'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*.path' => ['required', 'string', 'max:2048', 'regex:~\Aholidays/attachments/[A-Za-z0-9][A-Za-z0-9._-]*\z~D'],
            'attachments.*.name' => ['required', 'string', 'max:255', 'regex:~\A[^\x00-\x1F\x7F\\/]+\z~D'],
        ])->validate();

        foreach (['destination', 'accommodation', 'pricing_type', 'currency', 'pricing_notes', 'availability', 'booking_status', 'booking_instructions', 'booking_url', 'booking_contact', 'travel_details', 'itinerary_notes', 'featured_image_path'] as $field) {
            if (array_key_exists($field, $validated) && is_string($validated[$field])) {
                $validated[$field] = trim($validated[$field]) ?: null;
            }
        }
        if (is_string($validated['currency'] ?? null)) {
            $validated['currency'] = strtoupper($validated['currency']);
        }

        return Arr::only($validated, [
            'destination', 'accommodation', 'pricing_type', 'price_amount', 'currency', 'deposit_amount',
            'pricing_notes', 'capacity', 'availability', 'booking_deadline', 'booking_status',
            'booking_instructions', 'booking_url', 'booking_contact', 'travel_details', 'itinerary_notes',
            'featured_image_path', 'attachments',
        ]);
    }
}
