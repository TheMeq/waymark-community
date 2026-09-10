<?php

namespace App\Domain\Walks\Actions;

use App\Domain\Walks\Models\Walk;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class UpdateWalk
{
    public function __construct(private SaveWalkDetails $saveWalkDetails) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Walk $walk, User $actor, array $attributes): Walk
    {
        Gate::forUser($actor)->authorize('update', $walk);

        $walk->loadMissing(['event', 'coLeaders', 'tags']);
        $attributes = [
            ...$this->existingDetailAttributes($walk),
            ...$attributes,
        ];

        return DB::transaction(function () use ($walk, $attributes): Walk {
            $walk->event->fill(Arr::only($attributes, [
                'title',
                'slug',
                'summary',
                'description',
                'starts_at',
                'ends_at',
            ]));
            $walk->event->save();

            return $this->saveWalkDetails->handle($walk->event, $attributes);
        });
    }

    /** @return array<string, mixed> */
    private function existingDetailAttributes(Walk $walk): array
    {
        return [
            ...Arr::only($walk->attributesToArray(), [
                'primary_leader_id',
                'grade_id',
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
                'attachments',
                'private_organiser_notes',
                'recap',
                'highlights',
            ]),
            'co_leader_ids' => $walk->coLeaders->modelKeys(),
            'tag_ids' => $walk->tags->modelKeys(),
        ];
    }
}
