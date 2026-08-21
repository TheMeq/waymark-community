<?php

namespace App\Domain\Socials\Models;

use App\Domain\Events\Models\Event;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'event_id',
    'venue_name',
    'venue_address',
    'cost',
    'booking_status',
    'booking_instructions',
    'booking_url',
    'contact_name',
    'contact_details',
    'capacity',
    'availability',
    'accessibility_notes',
    'transport_notes',
    'attachments',
])]
final class Social extends Model
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
            'capacity' => 'integer',
            'attachments' => 'array',
        ];
    }
}
