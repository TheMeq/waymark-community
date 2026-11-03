<?php

namespace App\Domain\Accounts\Data;

use App\Domain\SiteMedia\Enums\ManagedImageSource;
use App\Rules\PublicImageReferenceRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

final readonly class ProfilePhotoInput
{
    private function __construct(
        public ManagedImageSource $source,
        public ?UploadedFile $upload,
        public ?string $externalUrl,
    ) {}

    public static function rules(): array
    {
        return [
            'profile_photo_source' => ['required', 'in:managed,external,none'],
            'profile_photo_upload' => ['exclude_unless:profile_photo_source,managed', 'nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/avif', 'max:'.max(1, (int) ceil(config('gallery.processing.max_upload_bytes', 10485760) / 1024))],
            'profile_photo_url' => ['exclude_unless:profile_photo_source,external', 'required_if:profile_photo_source,external', 'nullable', 'max:255', new PublicImageReferenceRule],
        ];
    }

    public static function from(array $state): self
    {
        $values = Validator::make($state, self::rules())->validate();

        return new self(
            ManagedImageSource::from($values['profile_photo_source']),
            $values['profile_photo_upload'] ?? null,
            $values['profile_photo_url'] ?? null,
        );
    }
}
