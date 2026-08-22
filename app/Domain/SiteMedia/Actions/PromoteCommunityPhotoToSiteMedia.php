<?php

namespace App\Domain\SiteMedia\Actions;

use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\PublicCommunityPhotoPresenter;
use App\Domain\SiteMedia\Data\SiteMediaMetadata;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PromoteCommunityPhotoToSiteMedia
{
    use ManagesSiteMedia;

    public function __construct(private readonly PublicCommunityPhotoPresenter $presenter) {}

    public function handle(User $actor, CommunityPhoto $photo, SiteMediaMetadata $metadata): SiteMedia
    {
        $this->authorizeSiteMedia($actor);
        $values = $this->validateMetadata($metadata);

        return DB::transaction(function () use ($actor, $photo, $values): SiteMedia {
            $locked = CommunityPhoto::query()->lockForUpdate()->findOrFail($photo->id);
            $path = $this->presenter->pathFor($locked, 'master');
            if ($path === null) {
                throw ValidationException::withMessages(['photo' => 'Choose an approved, currently published, processed photo with a safe derivative.']);
            }
            $key = Str::uuid()->toString();
            $disk = Storage::disk($locked->storage_disk);
            $contents = $disk->get($path);
            $image = @getimagesizefromstring($contents);
            if ($contents === false || ! is_array($image) || ! isset($image['mime'], $image[0], $image[1])) {
                throw ValidationException::withMessages(['photo' => 'The approved derivative could not be copied safely.']);
            }
            $extension = match (strtolower((string) $image['mime'])) {
                'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/avif' => 'avif', default => throw ValidationException::withMessages(['photo' => 'The approved derivative is not a supported raster image.'])
            };
            $destination = 'site-media/'.$key.'/master.'.$extension;
            if (! $disk->put($destination, $contents)) {
                $disk->deleteDirectory('site-media/'.$key);
                throw ValidationException::withMessages(['photo' => 'The approved derivative could not be copied safely.']);
            }
            $variants = ['master' => $destination];
            try {
                $media = SiteMedia::query()->create(array_merge($values, ['created_by_user_id' => $actor->id, 'source_community_photo_id' => $locked->id, 'storage_key' => $key, 'storage_disk' => $locked->storage_disk, 'processed_variants' => $variants, 'mime_type' => strtolower((string) $image['mime']), 'width' => (int) $image[0], 'height' => (int) $image[1], 'file_size_bytes' => strlen($contents), 'processing_status' => 'complete', 'health_status' => 'healthy']));
            } catch (\Throwable $exception) {
                $disk->deleteDirectory('site-media/'.$key);
                throw $exception;
            }
            $this->audit($actor, $media, 'promoted', [], $this->snapshot($media), ['source_community_photo_id' => $locked->id]);

            return $media;
        });
    }
}
