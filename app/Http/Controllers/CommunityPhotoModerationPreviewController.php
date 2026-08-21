<?php

namespace App\Http\Controllers;

use App\Domain\Gallery\Data\PhotoStorageReference;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\Queries\ModeratableCommunityPhotos;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

final class CommunityPhotoModerationPreviewController
{
    public function __invoke(CommunityPhoto $photo): Response
    {
        /** @var User $actor */
        $actor = auth()->user();
        abort_unless(app(ModeratableCommunityPhotos::class)->for($actor, ['pending', 'approved'])->whereKey($photo->id)->exists(), 403);
        $path = $photo->processed_variants['master'] ?? null;
        abort_unless(is_string($path), 404);
        abort_unless(PhotoStorageReference::isSafe((string) $photo->storage_disk, $path), 404);
        abort_unless(Storage::disk($photo->storage_disk)->exists($path), 404);

        return Storage::disk($photo->storage_disk)->response($path, null, ['Cache-Control' => 'private, no-store']);
    }
}
