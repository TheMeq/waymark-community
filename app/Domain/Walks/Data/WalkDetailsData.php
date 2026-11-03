<?php

namespace App\Domain\Walks\Data;

use App\Domain\Accounts\Queries\EligibleWalkLeadersQuery;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidationValidator;

final readonly class WalkDetailsData
{
    /**
     * @param  array<int, int>  $coLeaderIds
     * @param  array<int, int>  $tagIds
     * @param  array<int, string>|null  $kitChecklist
     * @param  array<int, array<string, mixed>>|null  $attachments
     */
    private function __construct(
        public int $primaryLeaderId,
        public ?int $gradeId,
        public array $coLeaderIds,
        public array $tagIds,
        public ?float $distance,
        public ?float $ascent,
        public ?int $estimatedDurationMinutes,
        public ?int $capacity,
        public bool $isPublicTransportFriendly,
        public ?string $publicTransportStationStop,
        public ?string $publicTransportNotes,
        public ?string $publicTransportUrl,
        public ?float $latitude,
        public ?float $longitude,
        public ?string $terrainNotes,
        public ?string $meetingLocationName,
        public ?string $meetingAddress,
        public ?string $meetingPostcode,
        public ?string $what3words,
        public ?string $osGridReference,
        public ?string $directions,
        public ?string $parkingNotes,
        public ?string $toiletInformation,
        public ?string $cafePubInformation,
        public ?string $dogGuidance,
        public ?string $accessibilityNotes,
        public ?array $kitChecklist,
        public ?string $kitNotes,
        public ?string $availability,
        public ?array $attachments,
        public ?string $privateOrganiserNotes,
        public ?string $recap,
        public ?string $highlights,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int>  $existingCoLeaderIds
     */
    public static function from(array $attributes, ?int $existingPrimaryLeaderId = null, array $existingCoLeaderIds = []): self
    {
        $attributes = self::normaliseEmptyStrings($attributes);

        $validator = Validator::make($attributes, [
            'primary_leader_id' => ['required', 'integer', 'exists:users,id'],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'co_leader_ids' => ['nullable', 'array', 'max:8'],
            'co_leader_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'tag_ids' => ['nullable', 'array', 'max:20'],
            'tag_ids.*' => ['integer', 'distinct', 'exists:tags,id'],
            'distance' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999.99'],
            'ascent' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'terrain_notes' => ['nullable', 'string', 'max:5000'],
            'meeting_location_name' => ['nullable', 'string', 'max:255'],
            'meeting_address' => ['nullable', 'string', 'max:5000'],
            'meeting_postcode' => ['nullable', 'string', 'max:32'],
            'what3words' => ['nullable', 'string', 'max:255'],
            'os_grid_reference' => ['nullable', 'string', 'max:255'],
            'directions' => ['nullable', 'string', 'max:5000'],
            'parking_notes' => ['nullable', 'string', 'max:5000'],
            'is_public_transport_friendly' => ['sometimes', 'boolean'],
            'public_transport_station_stop' => ['nullable', 'string', 'max:255'],
            'public_transport_notes' => ['nullable', 'string', 'max:5000'],
            'public_transport_url' => ['nullable', 'url', 'max:255'],
            'toilet_information' => ['nullable', 'string', 'max:5000'],
            'cafe_pub_information' => ['nullable', 'string', 'max:5000'],
            'dog_guidance' => ['nullable', 'string', 'max:5000'],
            'accessibility_notes' => ['nullable', 'string', 'max:5000'],
            'kit_checklist' => ['nullable', 'array', 'max:20'],
            'kit_checklist.*' => ['string', 'max:255'],
            'kit_notes' => ['nullable', 'string', 'max:5000'],
            'availability' => ['nullable', 'string', 'max:255'],
            'featured_image_media_id' => ['prohibited'],
            'featured_image_path' => ['prohibited'],
            'featured_image_alt_text' => ['prohibited'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*.path' => ['required', 'string', 'max:2048'],
            'attachments.*.name' => ['required', 'string', 'max:255'],
            'attachments.*.mime_type' => ['nullable', 'string', 'max:255'],
            'attachments.*.size_bytes' => ['nullable', 'integer', 'min:0'],
            'gpx_path' => ['prohibited'],
            'gpx_derived_metadata' => ['prohibited'],
            'private_organiser_notes' => ['nullable', 'string', 'max:10000'],
            'recap' => ['nullable', 'string', 'max:20000'],
            'highlights' => ['nullable', 'string', 'max:5000'],
        ]);

        $validator->after(function (ValidationValidator $validator) use ($attributes, $existingPrimaryLeaderId, $existingCoLeaderIds): void {
            $eligibleLeaders = app(EligibleWalkLeadersQuery::class);
            $primaryLeaderId = self::integerId($attributes['primary_leader_id'] ?? null);

            if ($primaryLeaderId !== null
                && $primaryLeaderId !== $existingPrimaryLeaderId
                && ! $eligibleLeaders->contains($primaryLeaderId)) {
                $validator->errors()->add('primary_leader_id', 'Choose an active, verified account with permission to manage walks.');
            }

            foreach ($attributes['co_leader_ids'] ?? [] as $coLeaderId) {
                $coLeaderId = self::integerId($coLeaderId);

                if ($coLeaderId !== null
                    && ! in_array($coLeaderId, $existingCoLeaderIds, true)
                    && ! $eligibleLeaders->contains($coLeaderId)) {
                    $validator->errors()->add('co_leader_ids', 'Choose only active, verified accounts with permission to manage walks.');
                    break;
                }
            }

            if (in_array($attributes['primary_leader_id'] ?? null, $attributes['co_leader_ids'] ?? [], true)) {
                $validator->errors()->add('co_leader_ids', 'The primary leader cannot also be a co-leader.');
            }

            foreach ($attributes['attachments'] ?? [] as $index => $attachment) {
                if (is_string($attachment['name'] ?? null) && ! WalkAttachment::isResponseSafeName($attachment['name'])) {
                    $validator->errors()->add("attachments.$index.name", 'Attachment filenames cannot contain path separators or control characters.');
                }
            }

            if (! ($attributes['is_public_transport_friendly'] ?? false)) {
                return;
            }

            $hasDetail = collect([
                $attributes['public_transport_station_stop'] ?? null,
                $attributes['public_transport_notes'] ?? null,
                $attributes['public_transport_url'] ?? null,
            ])->contains(fn (mixed $detail): bool => filled($detail));

            if (! $hasDetail) {
                $validator->errors()->add('public_transport', 'Provide public transport details for a transport-friendly walk.');
            }
        });

        $validated = $validator->validate();

        return new self(
            primaryLeaderId: $validated['primary_leader_id'],
            gradeId: $validated['grade_id'] ?? null,
            coLeaderIds: $validated['co_leader_ids'] ?? [],
            tagIds: $validated['tag_ids'] ?? [],
            distance: isset($validated['distance']) ? (float) $validated['distance'] : null,
            ascent: isset($validated['ascent']) ? (float) $validated['ascent'] : null,
            estimatedDurationMinutes: $validated['estimated_duration_minutes'] ?? null,
            capacity: $validated['capacity'] ?? null,
            isPublicTransportFriendly: $validated['is_public_transport_friendly'] ?? false,
            publicTransportStationStop: $validated['public_transport_station_stop'] ?? null,
            publicTransportNotes: $validated['public_transport_notes'] ?? null,
            publicTransportUrl: $validated['public_transport_url'] ?? null,
            latitude: isset($validated['latitude']) ? (float) $validated['latitude'] : null,
            longitude: isset($validated['longitude']) ? (float) $validated['longitude'] : null,
            terrainNotes: $validated['terrain_notes'] ?? null,
            meetingLocationName: $validated['meeting_location_name'] ?? null,
            meetingAddress: $validated['meeting_address'] ?? null,
            meetingPostcode: $validated['meeting_postcode'] ?? null,
            what3words: $validated['what3words'] ?? null,
            osGridReference: $validated['os_grid_reference'] ?? null,
            directions: $validated['directions'] ?? null,
            parkingNotes: $validated['parking_notes'] ?? null,
            toiletInformation: $validated['toilet_information'] ?? null,
            cafePubInformation: $validated['cafe_pub_information'] ?? null,
            dogGuidance: $validated['dog_guidance'] ?? null,
            accessibilityNotes: $validated['accessibility_notes'] ?? null,
            kitChecklist: $validated['kit_checklist'] ?? null,
            kitNotes: $validated['kit_notes'] ?? null,
            availability: $validated['availability'] ?? null,
            attachments: $validated['attachments'] ?? null,
            privateOrganiserNotes: $validated['private_organiser_notes'] ?? null,
            recap: $validated['recap'] ?? null,
            highlights: $validated['highlights'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    public function persistenceAttributes(): array
    {
        return [
            'grade_id' => $this->gradeId,
            'primary_leader_id' => $this->primaryLeaderId,
            'distance' => $this->distance,
            'ascent' => $this->ascent,
            'estimated_duration_minutes' => $this->estimatedDurationMinutes,
            'capacity' => $this->capacity,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'terrain_notes' => $this->terrainNotes,
            'meeting_location_name' => $this->meetingLocationName,
            'meeting_address' => $this->meetingAddress,
            'meeting_postcode' => $this->meetingPostcode,
            'what3words' => $this->what3words,
            'os_grid_reference' => $this->osGridReference,
            'directions' => $this->directions,
            'parking_notes' => $this->parkingNotes,
            'is_public_transport_friendly' => $this->isPublicTransportFriendly,
            'public_transport_station_stop' => $this->publicTransportStationStop,
            'public_transport_notes' => $this->publicTransportNotes,
            'public_transport_url' => $this->publicTransportUrl,
            'toilet_information' => $this->toiletInformation,
            'cafe_pub_information' => $this->cafePubInformation,
            'dog_guidance' => $this->dogGuidance,
            'accessibility_notes' => $this->accessibilityNotes,
            'kit_checklist' => $this->kitChecklist,
            'kit_notes' => $this->kitNotes,
            'availability' => $this->availability,
            'attachments' => $this->attachments,
            'private_organiser_notes' => $this->privateOrganiserNotes,
            'recap' => $this->recap,
            'highlights' => $this->highlights,
        ];
    }

    /** @param array<string, mixed> $attributes */
    private static function normaliseEmptyStrings(array $attributes): array
    {
        foreach ($attributes as $key => $value) {
            if (is_string($value)) {
                $attributes[$key] = ($value = trim($value)) === '' ? null : $value;
            }
        }

        return $attributes;
    }

    private static function integerId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }
}
