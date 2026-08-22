<?php

namespace App\Domain\SiteMedia\Actions;

use App\Domain\SiteMedia\Data\SiteMediaMetadata;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RegenerateSiteMedia
{
    use ManagesSiteMedia;

    public function __construct(private readonly PromoteCommunityPhotoToSiteMedia $promotion, private readonly SiteMediaNamespaceCleaner $cleaner) {}

    public function handle(User $actor, SiteMedia $media): SiteMedia
    {
        $this->authorizeSiteMedia($actor);
        if ($media->source_community_photo_id === null || $media->sourceCommunityPhoto === null) {
            throw ValidationException::withMessages(['media' => 'This media item has no safe source available for regeneration.']);
        }
        $replacement = $this->promotion->handle($actor, $media->sourceCommunityPhoto, new SiteMediaMetadata($media->alt_text, $media->is_decorative, (float) $media->focal_point_x, (float) $media->focal_point_y));

        $old = DB::transaction(function () use ($actor, $media, $replacement): array {
            $locked = SiteMedia::query()->lockForUpdate()->findOrFail($media->id);
            $before = $this->snapshot($locked);
            $old = ['storage_disk' => $locked->storage_disk, 'storage_key' => $locked->storage_key];
            // Free the unique replacement key inside this transaction. A later failure rolls this
            // deletion back, so neither the replacement record nor its audit trail is corrupted.
            $replacement->delete();
            $locked->forceFill(['storage_key' => $replacement->storage_key, 'storage_disk' => $replacement->storage_disk, 'processed_variants' => $replacement->processed_variants, 'mime_type' => $replacement->mime_type, 'width' => $replacement->width, 'height' => $replacement->height, 'file_size_bytes' => $replacement->file_size_bytes, 'health_status' => 'healthy', 'regeneration_cleanup_status' => 'pending', 'regeneration_cleanup_storage_disk' => $old['storage_disk'], 'regeneration_cleanup_storage_key' => $old['storage_key']])->save();
            $this->audit($actor, $locked, 'regenerated', $before, $this->snapshot($locked));

            return [$locked, $old];
        });

        $this->attemptCleanup($actor, $old[0]);

        return $old[0]->fresh();
    }

    public function retryCleanup(User $actor, SiteMedia $media): bool
    {
        $this->authorizeSiteMedia($actor);
        $locked = SiteMedia::query()->findOrFail($media->id);
        if ($locked->regeneration_cleanup_status === null) {
            return true;
        }

        return $this->attemptCleanup($actor, $locked);
    }

    private function attemptCleanup(User $actor, SiteMedia $media): bool
    {
        $disk = $media->regeneration_cleanup_storage_disk;
        $key = $media->regeneration_cleanup_storage_key;
        if (! is_string($disk) || ! is_string($key)) {
            return false;
        }

        $deleted = false;
        try {
            $deleted = $this->cleaner->delete($disk, $key);
        } catch (\Throwable) {
            $deleted = false;
        }

        DB::transaction(function () use ($actor, $media, $deleted): void {
            $locked = SiteMedia::query()->lockForUpdate()->findOrFail($media->id);
            if ($locked->regeneration_cleanup_storage_key === null) {
                return;
            }
            $before = $this->snapshot($locked);
            if ($deleted) {
                $locked->forceFill(['regeneration_cleanup_status' => null, 'regeneration_cleanup_storage_disk' => null, 'regeneration_cleanup_storage_key' => null])->save();
                $this->audit($actor, $locked, 'regeneration_cleanup_completed', $before, $this->snapshot($locked));

                return;
            }
            if ($locked->regeneration_cleanup_status !== 'failed') {
                $locked->forceFill(['regeneration_cleanup_status' => 'failed'])->save();
                $this->audit($actor, $locked, 'regeneration_cleanup_failed', $before, $this->snapshot($locked));
            }
        });

        return $deleted;
    }
}
