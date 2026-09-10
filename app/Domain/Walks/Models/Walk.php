<?php

namespace App\Domain\Walks\Models;

use App\Domain\Events\Models\Event;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'event_id',
    'grade_id',
    'primary_leader_id',
    'distance',
    'ascent',
    'estimated_duration_minutes',
    'capacity',
    'is_public_transport_friendly',
    'public_transport_station_stop',
    'public_transport_notes',
    'public_transport_url',
    'latitude',
    'longitude',
    'terrain_notes',
    'meeting_location_name',
    'meeting_address',
    'meeting_postcode',
    'what3words',
    'os_grid_reference',
    'directions',
    'parking_notes',
    'toilet_information',
    'cafe_pub_information',
    'dog_guidance',
    'accessibility_notes',
    'kit_checklist',
    'kit_notes',
    'availability',
    'featured_image_media_id',
    'featured_image_alt_text',
    'featured_image_path',
    'attachments',
    'gpx_path',
    'gpx_derived_metadata',
    'private_organiser_notes',
    'recap',
    'highlights',
])]
final class Walk extends Model
{
    protected static function booted(): void
    {
        self::updated(function (Walk $walk): void {
            if ($walk->wasChanged(['meeting_location_name', 'meeting_address', 'meeting_postcode', 'latitude', 'longitude'])) {
                $walk->event()->increment('calendar_sequence');
            }
        });
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<Grade, $this> */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /** @return BelongsTo<User, $this> */
    public function primaryLeader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_leader_id');
    }

    /** @return BelongsTo<SiteMedia, $this> */
    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(SiteMedia::class, 'featured_image_media_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function coLeaders(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'walk_co_leaders');
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'distance' => 'decimal:2',
            'ascent' => 'decimal:2',
            'estimated_duration_minutes' => 'integer',
            'capacity' => 'integer',
            'is_public_transport_friendly' => 'boolean',
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
            'kit_checklist' => 'array',
            'attachments' => 'array',
            'gpx_derived_metadata' => 'array',
        ];
    }
}
