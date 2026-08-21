<?php

namespace App\Domain\Holidays\Models;

use App\Domain\Events\Models\Event;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'event_id', 'show_child_events_in_global_calendar', 'destination', 'accommodation', 'pricing_type', 'price_amount', 'currency',
    'deposit_amount', 'pricing_notes', 'capacity', 'availability', 'booking_deadline',
    'booking_status', 'booking_instructions', 'booking_url', 'booking_contact', 'travel_details',
    'itinerary_notes', 'featured_image_path', 'attachments',
])]
final class Holiday extends Model
{
    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'show_child_events_in_global_calendar' => 'boolean',
            'price_amount' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'capacity' => 'integer',
            'booking_deadline' => 'datetime',
            'attachments' => 'array',
        ];
    }
}
