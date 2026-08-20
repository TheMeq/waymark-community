<?php

namespace App\Domain\Walks\Actions;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Walks\Data\DuplicateWalkOptions;
use App\Domain\Walks\Data\StoredGpx;
use App\Domain\Walks\Enums\DuplicateWalkCopyGroup;
use App\Domain\Walks\Models\Walk;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class DuplicateWalk
{
    public function __construct(private SaveWalkDetails $saveWalkDetails) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Walk $source, User $actor, array $attributes): Walk
    {
        Gate::forUser($actor)->authorize('duplicate', $source);

        $options = DuplicateWalkOptions::from($attributes);
        $source->loadMissing(['event', 'coLeaders', 'tags']);

        return DB::transaction(function () use ($source, $actor, $options): Walk {
            $event = Event::query()->create([
                'type' => EventType::Walk,
                'title' => $options->title,
                'slug' => $options->slug,
                'summary' => $options->copies(DuplicateWalkCopyGroup::CoreDetails) ? $source->event->summary : null,
                'description' => $options->copies(DuplicateWalkCopyGroup::CoreDetails) ? $source->event->description : null,
                'starts_at' => $options->startsAt,
                'ends_at' => $options->endsAt,
                'status' => EventStatus::Draft,
                'is_public' => false,
                'published_at' => null,
                'completion_override' => null,
                'organiser_id' => $actor->id,
            ]);

            $storedGpx = $options->copies(DuplicateWalkCopyGroup::Gpx)
                ? StoredGpx::fromWalk($source)
                : null;

            return $this->saveWalkDetails->handle($event, $this->walkAttributes($source, $actor, $options), $storedGpx);
        });
    }

    /** @return array<string, mixed> */
    private function walkAttributes(Walk $source, User $actor, DuplicateWalkOptions $options): array
    {
        $attributes = [
            'primary_leader_id' => $actor->id,
            'co_leader_ids' => [],
            'tag_ids' => [],
        ];

        if ($options->copies(DuplicateWalkCopyGroup::CoreDetails)) {
            $attributes = [
                ...$attributes,
                'grade_id' => $source->grade_id,
                'primary_leader_id' => $source->primary_leader_id,
                'co_leader_ids' => $source->coLeaders->modelKeys(),
                'tag_ids' => $source->tags->modelKeys(),
                ...$this->attributesFrom($source, [
                    'distance',
                    'ascent',
                    'estimated_duration_minutes',
                    'capacity',
                    'availability',
                ]),
            ];
        }

        if ($options->copies(DuplicateWalkCopyGroup::Location)) {
            $attributes = [
                ...$attributes,
                ...$this->attributesFrom($source, [
                    'latitude',
                    'longitude',
                    'meeting_location_name',
                    'meeting_address',
                    'meeting_postcode',
                    'what3words',
                    'os_grid_reference',
                    'directions',
                    'parking_notes',
                ]),
            ];
        }

        if ($options->copies(DuplicateWalkCopyGroup::Attachments)) {
            $attributes['attachments'] = $source->attachments;
        }

        if ($options->copies(DuplicateWalkCopyGroup::FeaturedImage)) {
            $attributes['featured_image_path'] = $source->featured_image_path;
        }

        if ($options->copies(DuplicateWalkCopyGroup::OptionalFields)) {
            $attributes = [
                ...$attributes,
                ...$this->attributesFrom($source, [
                    'terrain_notes',
                    'is_public_transport_friendly',
                    'public_transport_station_stop',
                    'public_transport_notes',
                    'public_transport_url',
                    'toilet_information',
                    'cafe_pub_information',
                    'dog_guidance',
                    'accessibility_notes',
                    'kit_checklist',
                    'kit_notes',
                    'private_organiser_notes',
                ]),
            ];
        }

        return $attributes;
    }

    /** @param array<int, string> $keys
     * @return array<string, mixed>
     */
    private function attributesFrom(Walk $walk, array $keys): array
    {
        $attributes = [];

        foreach ($keys as $key) {
            $attributes[$key] = $walk->{$key};
        }

        return $attributes;
    }
}
