<?php

namespace App\Domain\SiteMedia\Actions;

use App\Domain\SiteMedia\Enums\SiteMediaPurpose;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Domain\SiteMedia\Queries\SiteMediaUsage;
use Illuminate\Support\Facades\DB;

final class MarkSiteMediaOrphaned
{
    public function __construct(private readonly SiteMediaUsage $usage) {}

    public function handle(SiteMedia $media): bool
    {
        return DB::transaction(function () use ($media): bool {
            $locked = SiteMedia::query()->lockForUpdate()->findOrFail($media->id);

            if ($locked->purpose === SiteMediaPurpose::Library || $this->usage->isUsed($locked)) {
                return false;
            }

            if ($locked->orphaned_at === null) {
                $locked->forceFill(['orphaned_at' => now()])->save();
            }

            return true;
        });
    }
}
