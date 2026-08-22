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
        DB::transaction(function () use ($actor, $media): void {
            $locked = SiteMedia::query()->lockForUpdate()->findOrFail($media->id);
            $before = $this->snapshot($locked);
            $this->audit($actor, $locked, 'removed', $before);
            Storage::disk($locked->storage_disk)->deleteDirectory('site-media/'.$locked->storage_key);
            $locked->delete();
        });
    }
}
