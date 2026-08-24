<?php

namespace App\Domain\Operations\Health\Actions;

use App\Domain\Operations\Health\Models\MissingMediaRepair;
use App\Domain\SiteMedia\Models\SiteMedia;
use Illuminate\Support\Facades\Storage;

final class RecheckMissingMedia
{
    public function handle(MissingMediaRepair $repair): bool
    {
        $repair->forceFill(['last_checked_at' => now()]);

        if (! Storage::disk($repair->storage_disk)->exists($repair->path)) {
            $repair->save();

            return false;
        }

        $repair->forceFill(['status' => 'resolved', 'resolved_at' => now()])->save();

        if ($repair->media_type === SiteMedia::class && $repair->record_id !== null) {
            $media = SiteMedia::query()->find($repair->record_id);
            if ($media instanceof SiteMedia && $this->allVariantsExist($media)) {
                $media->forceFill(['health_status' => 'healthy'])->save();
            }
        }

        return true;
    }

    private function allVariantsExist(SiteMedia $media): bool
    {
        foreach ((array) $media->processed_variants as $path) {
            if (! is_string($path) || ! Storage::disk($media->storage_disk)->exists($path)) {
                return false;
            }
        }

        return true;
    }
}
