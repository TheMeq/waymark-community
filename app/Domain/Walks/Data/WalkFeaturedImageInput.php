<?php

namespace App\Domain\Walks\Data;

use App\Domain\SiteMedia\Enums\ManagedImageSource;
use App\Rules\PublicImageReferenceRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidationValidator;

final readonly class WalkFeaturedImageInput
{
    private function __construct(
        public ManagedImageSource $source,
        public ?UploadedFile $upload,
        public ?string $externalUrl,
        public ?string $altText,
    ) {}

    /** @param array<string, mixed> $state */
    public static function from(array $state): self
    {
        $state = self::normalise($state);
        $validator = Validator::make($state, [
            'featured_image_source' => ['required', Rule::enum(ManagedImageSource::class)],
            'featured_image_upload' => ['nullable', 'file'],
            'featured_image_external_url' => ['nullable', 'string', 'max:2048', new PublicImageReferenceRule],
            'featured_image_alt_text' => ['nullable', 'string', 'max:2000'],
        ]);

        $validator->after(static function (ValidationValidator $validator) use ($state): void {
            $source = ManagedImageSource::tryFrom((string) ($state['featured_image_source'] ?? ''));
            $upload = $state['featured_image_upload'] ?? null;
            $external = $state['featured_image_external_url'] ?? null;
            $alt = $state['featured_image_alt_text'] ?? null;

            if ($source === ManagedImageSource::Managed && $external !== null) {
                $validator->errors()->add('featured_image_external_url', 'A managed image and an external image cannot be selected together.');
            }

            if ($source === ManagedImageSource::External && $upload !== null) {
                $validator->errors()->add('featured_image_upload', 'A managed image and an external image cannot be selected together.');
            }

            if ($source === ManagedImageSource::External && $external === null) {
                $validator->errors()->add('featured_image_external_url', 'Enter a secure external image URL or site path.');
            }

            if (in_array($source, [ManagedImageSource::Managed, ManagedImageSource::External], true) && $alt === null) {
                $validator->errors()->add('featured_image_alt_text', 'Describe the image for people who cannot see it.');
            }

            if ($source === ManagedImageSource::None) {
                if ($upload !== null) {
                    $validator->errors()->add('featured_image_upload', 'Remove the pending upload before choosing no image.');
                }
                if ($external !== null) {
                    $validator->errors()->add('featured_image_external_url', 'Clear the external image before choosing no image.');
                }
                if ($alt !== null) {
                    $validator->errors()->add('featured_image_alt_text', 'Clear the image description when choosing no image.');
                }
            }
        });

        $validated = $validator->validate();

        return new self(
            source: ManagedImageSource::from($validated['featured_image_source']),
            upload: $validated['featured_image_upload'] ?? null,
            externalUrl: $validated['featured_image_external_url'] ?? null,
            altText: $validated['featured_image_alt_text'] ?? null,
        );
    }

    /** @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private static function normalise(array $state): array
    {
        foreach (['featured_image_source', 'featured_image_external_url'] as $key) {
            if (is_string($state[$key] ?? null) && trim($state[$key]) === '') {
                $state[$key] = null;
            }
        }

        if (is_string($state['featured_image_alt_text'] ?? null)) {
            $state['featured_image_alt_text'] = ($alt = trim($state['featured_image_alt_text'])) === '' ? null : $alt;
        }

        return $state;
    }
}
