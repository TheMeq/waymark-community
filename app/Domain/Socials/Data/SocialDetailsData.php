<?php

namespace App\Domain\Socials\Data;

use Illuminate\Support\Facades\Validator;

final readonly class SocialDetailsData
{
    /** @param array<int, array<string, mixed>>|null $attachments */
    private function __construct(
        public ?string $venueName,
        public ?string $venueAddress,
        public ?string $cost,
        public ?string $bookingStatus,
        public ?string $bookingInstructions,
        public ?string $bookingUrl,
        public ?string $contactName,
        public ?string $contactDetails,
        public ?int $capacity,
        public ?string $availability,
        public ?string $accessibilityNotes,
        public ?string $transportNotes,
        public ?array $attachments,
    ) {}

    /** @param array<string, mixed> $attributes */
    public static function from(array $attributes): self
    {
        $validator = Validator::make($attributes, [
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_address' => ['nullable', 'string', 'max:5000'],
            'cost' => ['nullable', 'string', 'max:255'],
            'booking_status' => ['nullable', 'string', 'max:255'],
            'booking_instructions' => ['nullable', 'string', 'max:5000'],
            'booking_url' => ['nullable', 'url:http,https', 'max:2048'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_details' => ['nullable', 'string', 'max:5000'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'availability' => ['nullable', 'string', 'max:255'],
            'accessibility_notes' => ['nullable', 'string', 'max:5000'],
            'transport_notes' => ['nullable', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*.path' => ['required', 'string', 'max:2048'],
            'attachments.*.name' => ['required', 'string', 'max:255'],
        ]);

        $validator->after(function ($validator) use ($attributes): void {
            foreach ($attributes['attachments'] ?? [] as $index => $attachment) {
                if (! is_array($attachment)) {
                    continue;
                }

                if (! self::safeAttachmentPath($attachment['path'] ?? null)) {
                    $validator->errors()->add("attachments.$index.path", 'Attachment paths must remain inside the Social attachment directory.');
                }

                if (! self::safeAttachmentName($attachment['name'] ?? null)) {
                    $validator->errors()->add("attachments.$index.name", 'Attachment filenames cannot contain path separators or control characters.');
                }
            }
        });

        $validated = $validator->validate();

        return new self(
            venueName: self::text($validated['venue_name'] ?? null),
            venueAddress: self::text($validated['venue_address'] ?? null),
            cost: self::text($validated['cost'] ?? null),
            bookingStatus: self::text($validated['booking_status'] ?? null),
            bookingInstructions: self::text($validated['booking_instructions'] ?? null),
            bookingUrl: self::text($validated['booking_url'] ?? null),
            contactName: self::text($validated['contact_name'] ?? null),
            contactDetails: self::text($validated['contact_details'] ?? null),
            capacity: isset($validated['capacity']) ? (int) $validated['capacity'] : null,
            availability: self::text($validated['availability'] ?? null),
            accessibilityNotes: self::text($validated['accessibility_notes'] ?? null),
            transportNotes: self::text($validated['transport_notes'] ?? null),
            attachments: $validated['attachments'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    public function persistenceAttributes(): array
    {
        return [
            'venue_name' => $this->venueName,
            'venue_address' => $this->venueAddress,
            'cost' => $this->cost,
            'booking_status' => $this->bookingStatus,
            'booking_instructions' => $this->bookingInstructions,
            'booking_url' => $this->bookingUrl,
            'contact_name' => $this->contactName,
            'contact_details' => $this->contactDetails,
            'capacity' => $this->capacity,
            'availability' => $this->availability,
            'accessibility_notes' => $this->accessibilityNotes,
            'transport_notes' => $this->transportNotes,
            'attachments' => $this->attachments,
        ];
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function safeAttachmentPath(mixed $path): bool
    {
        return is_string($path)
            && str_starts_with($path, 'socials/attachments/')
            && ! str_contains($path, '..')
            && ! str_contains($path, "\0")
            && preg_match('#^[A-Za-z0-9][A-Za-z0-9._/-]*$#D', $path) === 1;
    }

    private static function safeAttachmentName(mixed $name): bool
    {
        return is_string($name)
            && trim($name) !== ''
            && strlen(trim($name)) <= 255
            && ! str_contains($name, '/')
            && ! str_contains($name, '\\')
            && preg_match('/[\x00-\x1F\x7F]/', $name) !== 1;
    }
}
