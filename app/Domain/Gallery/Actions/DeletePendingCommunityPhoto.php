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
        [$disk, $paths, $outputDirectory, $referencesAreSafe] = DB::transaction(function () use ($actor, $photo): array {
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
            // A legacy/corrupt storage reference must remain repairable; bypass the
            // model's storage-reference guard only for this narrow state transition.
            DB::table('community_photos')->where('id', $locked->id)->update(['processing_status' => 'deleting', 'updated_at' => now()]);

            $disk = (string) $locked->storage_disk;
            $paths = array_merge([$locked->getRawOriginal('source_path')], $this->variants($locked), [$job?->staged_source_path]);
            $outputDirectory = $job?->output_directory;
            $referencesAreSafe = $this->safeReferences($disk, $paths, $outputDirectory);

            /** @var list<string> $safePaths */
            $safePaths = $referencesAreSafe ? array_values(array_filter($paths, static fn (mixed $path): bool => $path !== null)) : [];

            return [$disk, array_values(array_unique($safePaths)), $outputDirectory, $referencesAreSafe];
        });

        if (! $referencesAreSafe) {
            throw new \RuntimeException('The pending photo needs administrator repair before it can be deleted.');
        }

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

    /** @return list<mixed> */
    private function variants(CommunityPhoto $photo): array
    {
        $raw = $photo->getRawOriginal('processed_variants');
        if ($raw === null) {
            return [];
        }
        $variants = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($variants) ? array_values($variants) : [$raw];
    }

    /** @param list<mixed> $paths */
    private function safeReferences(string $disk, array $paths, mixed $outputDirectory): bool
    {
        foreach ($paths as $path) {
            if ($path !== null && (! is_string($path) || ! PhotoStorageReference::isSafe($disk, $path))) {
                return false;
            }
        }

        return $outputDirectory === null || (is_string($outputDirectory) && PhotoStorageReference::isSafeDirectory($disk, $outputDirectory));
    }
}
