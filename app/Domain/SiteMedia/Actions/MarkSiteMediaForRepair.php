<?php

namespace App\Domain\SiteMedia\Actions;

use App\Domain\SiteMedia\Data\SiteMediaStorageReference;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class MarkSiteMediaForRepair
{
    use ManagesSiteMedia;

    public function handle(User $actor, SiteMedia $media): SiteMedia
    {
        $this->authorizeSiteMedia($actor);

        return DB::transaction(function () use ($actor, $media): SiteMedia {
            $locked = SiteMedia::query()->lockForUpdate()->findOrFail($media->id);
            $variants = (array) $locked->processed_variants;
            $requiredVariants = array_keys((array) config('gallery.processing.variants', ['master' => []]));
            $missing = $locked->processing_status !== 'complete'
                || collect($requiredVariants)->contains(fn (string $name): bool => ! array_key_exists($name, $variants))
                || collect($variants)->contains(fn ($path): bool => ! is_string($path) || ! SiteMediaStorageReference::isSafe($locked->storage_disk, $path) || ! Storage::disk($locked->storage_disk)->exists($path));
            if (! $missing || $locked->health_status === 'repair_required') {
                return $locked;
            }
            $before = $this->snapshot($locked);
            $locked->forceFill(['health_status' => 'repair_required'])->save();
            $this->audit($actor, $locked, 'repair_required', $before, $this->snapshot($locked));

            return $locked;
        });
    }
}
