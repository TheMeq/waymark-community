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

    public function __construct(private readonly PromoteCommunityPhotoToSiteMedia $promotion) {}

    public function handle(User $actor, SiteMedia $media): SiteMedia
    {
        $this->authorizeSiteMedia($actor);
        if ($media->source_community_photo_id === null || $media->sourceCommunityPhoto === null) {
            throw ValidationException::withMessages(['media' => 'This media item has no safe source available for regeneration.']);
        }
        $replacement = $this->promotion->handle($actor, $media->sourceCommunityPhoto, new SiteMediaMetadata($media->alt_text, $media->is_decorative, (float) $media->focal_point_x, (float) $media->focal_point_y));

        return DB::transaction(function () use ($actor, $media, $replacement): SiteMedia {
            $locked = SiteMedia::query()->lockForUpdate()->findOrFail($media->id);
            $before = $this->snapshot($locked);
            $locked->forceFill(['storage_key' => $replacement->storage_key, 'storage_disk' => $replacement->storage_disk, 'processed_variants' => $replacement->processed_variants, 'mime_type' => $replacement->mime_type, 'width' => $replacement->width, 'height' => $replacement->height, 'file_size_bytes' => $replacement->file_size_bytes, 'health_status' => 'healthy'])->save();
            $this->audit($actor, $locked, 'regenerated', $before, $this->snapshot($locked));
            $replacement->delete();

            return $locked;
        });
    }
}
