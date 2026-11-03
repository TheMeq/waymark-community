<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Data\ProfilePhotoInput;
use App\Domain\Accounts\Models\ProfilePhoto;
use App\Domain\Gallery\Actions\IngestCommunityPhoto;
use App\Domain\Gallery\Contracts\RasterImageTransformer;
use App\Domain\Gallery\Data\ImageProcessingConfiguration;
use App\Domain\SiteMedia\Enums\ManagedImageSource;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class UpdateProfilePhoto
{
    public function __construct(
        private IngestCommunityPhoto $ingest,
        private RasterImageTransformer $transformer,
        private DeleteProfilePhotoFiles $cleanup,
    ) {}

    public function handle(User $member, ProfilePhotoInput $input): void
    {
        $this->authorize($member->fresh());
        $created = null;
        if ($input->source === ManagedImageSource::Managed && $input->upload !== null) {
            $configuration = ImageProcessingConfiguration::from(array_replace((array) config('gallery.processing'), [
                'source_retention' => false,
                'variants' => [
                    'medium' => ['max_width' => 640, 'max_height' => 640, 'quality' => 84],
                    'thumbnail' => ['max_width' => 160, 'max_height' => 160, 'quality' => 82],
                ],
                'required_variants' => ['medium', 'thumbnail'],
            ]), $this->transformer);
            try {
                $processed = $this->ingest->handle($input->upload, 'profile-photos/'.Str::uuid()->toString(), $configuration, (string) config('accounts.profile_photos.disk', 'local'));
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages(['profile_photo_upload' => collect($exception->errors())->flatten()->all()]);
            }
            $created = new ProfilePhoto([
                'id' => Str::uuid()->toString(), 'user_id' => $member->id,
                'storage_disk' => (string) config('accounts.profile_photos.disk', 'local'),
                'processed_variants' => array_map(fn ($variant): string => $variant->path, $processed->variants),
                'mime_type' => $processed->variants['thumbnail']->mimeType,
            ]);
        }

        try {
            $former = DB::transaction(function () use ($member, $input, $created): ?ProfilePhoto {
                $locked = User::query()->lockForUpdate()->findOrFail($member->id);
                $this->authorize($locked);
                $former = ProfilePhoto::query()->where('user_id', $locked->id)->lockForUpdate()->first();
                if ($input->source === ManagedImageSource::Managed && $created === null) {
                    if ($former === null || $former->variantPath('thumbnail') === null) {
                        throw ValidationException::withMessages(['profile_photo_upload' => 'Choose a photo to upload.']);
                    }

                    return null;
                }
                $former?->forceFill(['user_id' => null])->save();
                $created?->save();
                $locked->forceFill(['profile_photo_reference' => $input->source === ManagedImageSource::External ? $input->externalUrl : null])->save();

                return $former;
            });
        } catch (\Throwable $exception) {
            if ($created !== null) {
                $this->cleanup->handle($created);
            }
            throw $exception;
        }
        if ($former !== null) {
            $this->cleanup->handle($former);
        }
        $member->refresh();
    }

    private function authorize(?User $member): void
    {
        if ($member === null || ! $member->isActive() || ! $member->hasVerifiedEmail()) {
            throw new AuthorizationException;
        }
    }
}
