<?php

namespace App\Domain\Operations\Data;

use App\Domain\Content\Presentation\PublicImageReference;
use App\Domain\SiteMedia\Enums\ManagedImageSource;
use App\Domain\SiteMedia\Enums\SiteMediaPurpose;
use App\Rules\PublicImageReferenceRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidationValidator;

final readonly class BrandingImageInput
{
    private function __construct(
        public SiteMediaPurpose $purpose,
        public ManagedImageSource $source,
        public ?UploadedFile $upload,
        public ?string $externalUrl,
        public bool $removeFallback,
    ) {}

    /** @param array<string, mixed> $state */
    public static function from(array $state, SiteMediaPurpose $purpose): self
    {
        $slot = self::slotFor($purpose);
        $sourceKey = $slot.'_source';
        $uploadKey = $slot.'_upload';
        $pathKey = $slot.'_path';
        $removeKey = $slot.'_remove_fallback';
        $state = self::normalise($state, $sourceKey, $pathKey);
        $source = ManagedImageSource::tryFrom((string) ($state[$sourceKey] ?? ''));
        $pathRules = ['nullable', 'string', 'max:2048'];

        if ($source === ManagedImageSource::External) {
            $pathRules[] = new PublicImageReferenceRule;
        }

        $validator = Validator::make($state, [
            $sourceKey => ['required', Rule::enum(ManagedImageSource::class)],
            $uploadKey => ['nullable', 'file'],
            $pathKey => $pathRules,
            $removeKey => ['sometimes', 'boolean'],
        ]);

        $validator->after(static function (ValidationValidator $validator) use ($state, $sourceKey, $uploadKey, $pathKey): void {
            $source = ManagedImageSource::tryFrom((string) ($state[$sourceKey] ?? ''));
            $upload = $state[$uploadKey] ?? null;
            $external = $state[$pathKey] ?? null;

            if ($source === ManagedImageSource::External && $upload !== null) {
                $validator->errors()->add($uploadKey, 'A managed image and an external image cannot be selected together.');
            }

            if ($source === ManagedImageSource::External && $external === null) {
                $validator->errors()->add($pathKey, 'Enter a secure external image URL or site path.');
            }

            if ($source === ManagedImageSource::None && $upload !== null) {
                $validator->errors()->add($uploadKey, 'Remove the pending upload before choosing no image.');
            }
        });

        $validated = $validator->validate();

        return new self(
            purpose: $purpose,
            source: ManagedImageSource::from($validated[$sourceKey]),
            upload: $validated[$uploadKey] ?? null,
            externalUrl: isset($validated[$pathKey]) && PublicImageReference::isAllowed($validated[$pathKey])
                ? $validated[$pathKey]
                : null,
            removeFallback: (bool) ($validated[$removeKey] ?? false),
        );
    }

    private static function slotFor(SiteMediaPurpose $purpose): string
    {
        return match ($purpose) {
            SiteMediaPurpose::SiteLogo => 'logo',
            SiteMediaPurpose::SiteFavicon => 'favicon',
            default => throw ValidationException::withMessages([
                'branding' => 'Branding images must use the logo or favicon purpose.',
            ]),
        };
    }

    /** @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private static function normalise(array $state, string $sourceKey, string $pathKey): array
    {
        foreach ([$sourceKey, $pathKey] as $key) {
            if (is_string($state[$key] ?? null) && trim($state[$key]) === '') {
                $state[$key] = null;
            }
        }

        return $state;
    }
}
