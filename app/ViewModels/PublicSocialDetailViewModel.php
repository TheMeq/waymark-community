<?php

namespace App\ViewModels;

use App\Domain\Events\Models\Event;
use App\Domain\Socials\Data\SocialAttachment;

final readonly class PublicSocialDetailViewModel
{
    /** @return array<string, mixed> */
    public static function fromEvent(Event $event): array
    {
        $social = $event->social;

        return [
            'title' => $event->title,
            'summary' => $event->summary,
            'description' => $event->description,
            'starts_at' => $event->starts_at->format('l j F Y, H:i'),
            'ends_at' => $event->ends_at?->format('l j F Y, H:i'),
            'status' => $event->status->value === 'changed' ? 'Updated details' : ucfirst($event->status->value),
            'organiser' => $event->organiser?->name,
            'venue' => array_filter(['name' => $social?->venue_name, 'address' => $social?->venue_address]),
            'cost' => $social?->cost,
            'booking' => array_filter([
                'status' => $social?->booking_status,
                'instructions' => $social?->booking_instructions,
                'url' => $social?->booking_url,
                'contact_name' => $social?->contact_name,
                'contact_details' => $social?->contact_details,
            ]),
            'capacity' => $social?->capacity,
            'availability' => $social?->availability,
            'accessibility' => $social?->accessibility_notes,
            'transport' => $social?->transport_notes,
            'attachments' => $social === null ? [] : SocialAttachment::availableFor($social),
        ];
    }
}
