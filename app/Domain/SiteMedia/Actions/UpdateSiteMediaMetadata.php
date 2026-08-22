<?php

namespace App\Domain\SiteMedia\Actions;

use App\Domain\SiteMedia\Data\SiteMediaMetadata;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateSiteMediaMetadata
{
    use ManagesSiteMedia;

    public function handle(User $actor, SiteMedia $media, SiteMediaMetadata $metadata): SiteMedia
    {
        $this->authorizeSiteMedia($actor);
        $values = $this->validateMetadata($metadata);

        return DB::transaction(function () use ($actor, $media, $values): SiteMedia {
            $locked = SiteMedia::query()->lockForUpdate()->findOrFail($media->id);
            $before = $this->snapshot($locked);
            if (array_intersect_assoc($values, $before) !== $values) {
                $locked->forceFill($values)->save();
                $this->audit($actor, $locked, 'metadata_updated', $before, $this->snapshot($locked));
            }

            return $locked;
        });
    }
}
