<?php

namespace App\Domain\Gallery;

use App\Domain\Gallery\Data\CommunityPhotoModerationPreview;
use App\Domain\Gallery\Data\PhotoStorageReference;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\Queries\ModeratableCommunityPhotos;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

final class CommunityPhotoModerationPreviewResolver
{
    public function resolve(User $actor, CommunityPhoto $photo): ?CommunityPhotoModerationPreview
    {
        if ($photo->processing_status !== 'complete'
            || ! $this->canAccess($actor, $photo)) {
            return null;
        }

        $disk = (string) $photo->storage_disk;
        $path = $photo->processed_variants['master'] ?? null;
        if (! is_string($path)
            || ! array_key_exists($disk, (array) config('filesystems.disks'))
            || ! PhotoStorageReference::isSafe($disk, $path)
            || ! Storage::disk($disk)->exists($path)) {
            return null;
        }

        $description = $photo->caption ?: ($photo->event?->title ?? $photo->specialAlbum?->title ?? 'Community photo');

        return new CommunityPhotoModerationPreview(
            $disk,
            $path,
            route('admin.photo-moderation.preview', $photo),
            'Preview: '.$description,
        );
    }

    public function canAccess(User $actor, CommunityPhoto $photo): bool
    {
        return app(ModeratableCommunityPhotos::class)->for($actor, ['pending', 'approved'])->whereKey($photo->id)->exists();
    }
}
