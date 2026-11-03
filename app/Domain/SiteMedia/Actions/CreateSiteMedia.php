<?php

namespace App\Domain\SiteMedia\Actions;

use App\Domain\Gallery\Actions\IngestCommunityPhoto;
use App\Domain\SiteMedia\Data\SiteMediaMetadata;
use App\Domain\SiteMedia\Data\SiteMediaStorageReference;
use App\Domain\SiteMedia\Enums\SiteMediaPurpose;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Domain\SiteMedia\Models\SiteMediaAudit;
use App\Domain\SiteMedia\Support\SiteMediaProcessingProfiles;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateSiteMedia
{
    public function __construct(
        private readonly IngestCommunityPhoto $ingest,
        private readonly SiteMediaProcessingProfiles $profiles,
        private readonly SiteMediaNamespaceCleaner $cleaner,
    ) {}

    public function handle(
        User $actor,
        UploadedFile $upload,
        SiteMediaMetadata $metadata,
        SiteMediaPurpose $purpose,
    ): SiteMedia {
        $values = $this->validatedMetadata($metadata, $purpose);
        $configuration = $this->profiles->configurationFor($purpose);
        $disk = (string) config('gallery.photos.disk', 'local');
        $key = Str::uuid()->toString();

        try {
            $processed = $this->ingest->handle($upload, 'site-media/'.$key, $configuration);
            $variants = [];

            foreach ($processed->variants as $name => $variant) {
                if (! SiteMediaStorageReference::isSafe($disk, $variant->path)) {
                    throw new \InvalidArgumentException('Processed SiteMedia must use a generated private namespace.');
                }

                $variants[$name] = $variant->path;
            }

            $source = $processed->retainedSource ?? (array_values($processed->variants)[0] ?? null);

            if ($source === null) {
                throw new \RuntimeException('SiteMedia processing produced no required variants.');
            }

            return DB::transaction(function () use ($actor, $values, $purpose, $key, $disk, $variants, $source): SiteMedia {
                $media = SiteMedia::query()->create(array_merge($values, [
                    'created_by_user_id' => $actor->id,
                    'storage_key' => $key,
                    'storage_disk' => $disk,
                    'processed_variants' => $variants,
                    'mime_type' => $source->mimeType,
                    'width' => $source->width,
                    'height' => $source->height,
                    'file_size_bytes' => $source->fileSizeBytes,
                    'processing_status' => 'complete',
                    'health_status' => 'healthy',
                    'purpose' => $purpose,
                    'orphaned_at' => $purpose === SiteMediaPurpose::Library ? null : now(),
                ]));
                SiteMediaAudit::query()->create([
                    'site_media_id' => $media->id,
                    'actor_user_id' => $actor->id,
                    'action' => 'uploaded',
                    'before' => null,
                    'after' => $this->snapshot($media),
                    'context' => null,
                ]);

                return $media;
            });
        } catch (\Throwable $exception) {
            try {
                $this->cleaner->delete($disk, $key);
            } catch (\Throwable) {
                // Preserve the original processing or persistence failure.
            }

            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    private function validatedMetadata(SiteMediaMetadata $metadata, SiteMediaPurpose $purpose): array
    {
        $requiredDecorativeState = $purpose->requiredDecorativeState();
        $isDecorative = $requiredDecorativeState ?? $metadata->isDecorative;
        $alt = trim((string) $metadata->altText);

        if (! $isDecorative && $alt === '') {
            throw ValidationException::withMessages(['alt_text' => 'Describe the image or mark it decorative.']);
        }
        if (mb_strlen($alt) > 2000) {
            throw ValidationException::withMessages(['alt_text' => 'Alt text is too long.']);
        }
        if ($metadata->focalPointX < 0 || $metadata->focalPointX > 1 || $metadata->focalPointY < 0 || $metadata->focalPointY > 1) {
            throw ValidationException::withMessages(['focal_point' => 'Focal point must be within the image.']);
        }

        return [
            'alt_text' => $isDecorative ? null : $alt,
            'is_decorative' => $isDecorative,
            'focal_point_x' => $metadata->focalPointX,
            'focal_point_y' => $metadata->focalPointY,
        ];
    }

    /** @return array<string, mixed> */
    private function snapshot(SiteMedia $media): array
    {
        return $media->only([
            'alt_text',
            'is_decorative',
            'focal_point_x',
            'focal_point_y',
            'health_status',
            'purpose',
            'orphaned_at',
            'processed_variants',
            'regeneration_cleanup_status',
            'regeneration_cleanup_storage_disk',
            'regeneration_cleanup_storage_key',
        ]);
    }
}
