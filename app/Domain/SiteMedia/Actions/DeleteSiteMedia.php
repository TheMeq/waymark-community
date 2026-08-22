<?php

namespace App\Domain\SiteMedia\Actions;

use App\Domain\SiteMedia\Models\SiteMedia;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class DeleteSiteMedia
{
    use ManagesSiteMedia;

    public function handle(User $actor, SiteMedia $media): void
    {
        $this->authorizeSiteMedia($actor);
        $directory = DB::transaction(function () use ($actor, $media): string {
            $locked = SiteMedia::query()->lockForUpdate()->findOrFail($media->id);
            $before = $this->snapshot($locked);
            $locked->forceFill(['processing_status' => 'removed', 'health_status' => 'deletion_pending', 'processed_variants' => []])->save();
            $this->audit($actor, $locked, 'removal_requested', $before, $this->snapshot($locked));

            return 'site-media/'.$locked->storage_key;
        });
        $this->cleanup($actor, SiteMedia::query()->findOrFail($media->id), $directory);
    }

    public function retry(User $actor, SiteMedia $media): void
    {
        $this->authorizeSiteMedia($actor);
        $locked = SiteMedia::query()->findOrFail($media->id);
        if (! in_array($locked->health_status, ['deletion_pending', 'deletion_failed'], true)) {
            return;
        }
        $this->cleanup($actor, $locked, 'site-media/'.$locked->storage_key);
    }

    private function cleanup(User $actor, SiteMedia $media, string $directory): void
    {
        try {
            if (! Storage::disk($media->storage_disk)->deleteDirectory($directory)) {
                throw new \RuntimeException('Site media files could not be deleted.');
            }
        } catch (\Throwable) {
            DB::transaction(function () use ($actor, $media): void {
                $locked = SiteMedia::query()->lockForUpdate()->findOrFail($media->id);
                if ($locked->health_status !== 'deletion_failed') {
                    $before = $this->snapshot($locked);
                    $locked->forceFill(['health_status' => 'deletion_failed'])->save();
                    $this->audit($actor, $locked, 'deletion_failed', $before, $this->snapshot($locked));
                }
            });

            return;
        }
        DB::transaction(function () use ($actor, $media): void {
            $locked = SiteMedia::query()->lockForUpdate()->findOrFail($media->id);
            $before = $this->snapshot($locked);
            $locked->forceFill(['health_status' => 'removed'])->save();
            $this->audit($actor, $locked, 'removed', $before, $this->snapshot($locked));
        });
    }
}
