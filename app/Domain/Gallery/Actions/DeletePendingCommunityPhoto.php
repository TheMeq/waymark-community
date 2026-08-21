<?php

namespace App\Domain\Gallery\Actions;

use App\Domain\Gallery\Data\PhotoStorageReference;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\Models\CommunityPhotoProcessingJob;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class DeletePendingCommunityPhoto
{
    public function handle(User $actor, CommunityPhoto $photo): void
    {
        [$disk, $paths, $outputDirectory] = DB::transaction(function () use ($actor, $photo): array {
            // Match the deferred worker order: processing job, then photo.
            $job = CommunityPhotoProcessingJob::query()->where('community_photo_id', $photo->id)->lockForUpdate()->first();
            $locked = CommunityPhoto::query()->lockForUpdate()->findOrFail($photo->id);
            if ($locked->uploader_id !== $actor->id) {
                throw new AuthorizationException;
            }
            if ($locked->moderation_status !== 'pending') {
                throw ValidationException::withMessages(['photo' => 'Only pending photos can be deleted.']);
            }
            if ($job !== null) {
                $job->update(['status' => 'cancelled', 'claim_token' => null, 'lease_expires_at' => null, 'available_at' => null, 'failure_reason' => 'Cancelled by uploader.']);
            }
            $locked->update(['processing_status' => 'deleting']);

            return [(string) $locked->storage_disk, array_values(array_unique(array_filter(array_merge([$locked->source_path], array_values($locked->processed_variants ?? []), [$job?->staged_source_path]), fn ($path): bool => is_string($path) && PhotoStorageReference::isSafe($locked->storage_disk, $path)))), $job?->output_directory];
        });

        foreach ($paths as $path) {
            if (! Storage::disk($disk)->delete($path)) {
                throw new \RuntimeException('The pending photo could not be safely deleted. Please try again.');
            }
        }
        if (is_string($outputDirectory) && PhotoStorageReference::isSafeDirectory($disk, $outputDirectory) && ! Storage::disk($disk)->deleteDirectory($outputDirectory)) {
            throw new \RuntimeException('The pending photo could not be safely deleted. Please try again.');
        }

        DB::transaction(function () use ($photo): void {
            $job = CommunityPhotoProcessingJob::query()->where('community_photo_id', $photo->id)->lockForUpdate()->first();
            $locked = CommunityPhoto::query()->lockForUpdate()->findOrFail($photo->id);
            if ($locked->processing_status !== 'deleting') {
                return;
            }
            if ($job !== null) {
                $job->delete();
            }
            $locked->delete();
        });
    }
}
