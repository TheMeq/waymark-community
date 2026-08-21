<?php

namespace App\ViewModels;

use App\Domain\Events\Models\Event;
use App\Domain\Holidays\Data\HolidayAttachment;
use App\Domain\Holidays\Data\HolidayFeaturedImage;

final readonly class PublicHolidayDetailViewModel
{
    /** @return array<string, mixed> */
    public static function fromEvent(Event $event): array
    {
        $holiday = $event->holiday;

        return [
            'destination' => $holiday?->destination,
            'accommodation' => $holiday?->accommodation,
            'organiser' => $event->organiser?->name,
            'pricing' => self::pricing($holiday?->pricing_type, $holiday?->price_amount, $holiday?->currency, $holiday?->deposit_amount, $holiday?->pricing_notes),
            'capacity' => $holiday?->capacity,
            'availability' => $holiday?->availability,
            'booking' => array_filter([
                'deadline' => $holiday?->booking_deadline?->format('l j F Y, H:i'),
                'status' => $holiday?->booking_status,
                'instructions' => $holiday?->booking_instructions,
                'url' => $holiday?->booking_url,
                'contact' => $holiday?->booking_contact,
            ]),
            'travel' => $holiday?->travel_details,
            'itinerary' => $holiday?->itinerary_notes,
            'image' => HolidayFeaturedImage::resolve($holiday?->featured_image_path)?->toArray(),
            'attachments' => $holiday === null ? [] : HolidayAttachment::availableFor($holiday),
        ];
    }

    /** @return array<int, string> */
    private static function pricing(?string $type, ?string $amount, ?string $currency, ?string $deposit, ?string $notes): array
    {
        $symbol = match ($currency) {
            'GBP' => '£', 'EUR' => '€', 'USD' => '$', default => $currency ? $currency.' ' : ''
        };
        $price = match ($type) {
            'free' => 'Free',
            'tbc' => 'Price TBC',
            'fixed' => $amount === null ? null : $symbol.$amount,
            'from' => $amount === null ? null : 'From '.$symbol.$amount,
            default => null,
        };

        return array_values(array_filter([$price, $deposit === null ? null : 'Deposit '.$symbol.$deposit, $notes]));
    }
}
