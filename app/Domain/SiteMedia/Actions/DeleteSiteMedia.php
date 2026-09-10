<?php

namespace App\Domain\SiteMedia\Actions;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\SiteMedia\Enums\SiteMediaPurpose;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Domain\SiteMedia\Queries\SiteMediaUsage;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeleteSiteMedia
{
    use ManagesSiteMedia;

    public function __construct(
        private readonly SiteMediaUsage $usage,
        private readonly SiteMediaNamespaceCleaner $cleaner,
    ) {}

    public function handle(User $actor, SiteMedia $media): bool
    {
        $this->authorizeSiteMedia($actor);

        return DB::transaction(function () use ($actor, $media): bool {
            $locked = SiteMedia::query()->lockForUpdate()->findOrFail($media->id);
            $this->refuseIfUsed($locked);

            return $this->removeLocked($actor, $locked);
        });
    }

    public function retry(User $actor, SiteMedia $media): bool
    {
        $this->authorizeSiteMedia($actor);

        return DB::transaction(function () use ($actor, $media): bool {
            $locked = SiteMedia::query()->lockForUpdate()->findOrFail($media->id);
            $this->refuseIfUsed($locked);

            if (! in_array($locked->health_status, ['deletion_pending', 'deletion_failed'], true)) {
                return $locked->health_status === 'removed';
            }

            return $this->cleanupLocked($actor, $locked);
        });
    }

    public function discardOrphan(User $actor, SiteMedia $media): bool
    {
        return DB::transaction(function () use ($actor, $media): bool {
            $locked = SiteMedia::query()->lockForUpdate()->findOrFail($media->id);
            $this->authorizeOrphanDiscard($actor, $locked);

            if ($locked->purpose === SiteMediaPurpose::Library || $locked->orphaned_at === null || $this->usage->isUsed($locked)) {
                return false;
            }

            return $this->removeLocked($actor, $locked);
        });
    }

    private function authorizeOrphanDiscard(User $actor, SiteMedia $media): void
    {
        if (
            ! $actor->hasCapability(ModuleCapability::ManageSiteMedia)
            && (int) $media->created_by_user_id !== (int) $actor->id
        ) {
            throw new AuthorizationException;
        }
    }

    private function removeLocked(User $actor, SiteMedia $media): bool
    {
        if (! in_array($media->health_status, ['deletion_pending', 'deletion_failed'], true)) {
            $before = $this->snapshot($media);
            $media->forceFill(['processing_status' => 'removed', 'health_status' => 'deletion_pending', 'processed_variants' => []])->save();
            $this->audit($actor, $media, 'removal_requested', $before, $this->snapshot($media));
        }

        return $this->cleanupLocked($actor, $media);
    }

    private function cleanupLocked(User $actor, SiteMedia $media): bool
    {
        if ($this->usage->isUsed($media)) {
            return false;
        }

        try {
            if (! $this->cleaner->delete($media->storage_disk, $media->storage_key)) {
                throw new \RuntimeException('Site media files could not be deleted.');
            }
        } catch (\Throwable) {
            if ($media->health_status !== 'deletion_failed') {
                $before = $this->snapshot($media);
                $media->forceFill(['health_status' => 'deletion_failed'])->save();
                $this->audit($actor, $media, 'deletion_failed', $before, $this->snapshot($media));
            }

            return false;
        }

        $before = $this->snapshot($media);
        $media->forceFill(['health_status' => 'removed'])->save();
        $this->audit($actor, $media, 'removed', $before, $this->snapshot($media));

        return true;
    }

    private function refuseIfUsed(SiteMedia $media): void
    {
        $labels = $this->usage->labelsFor($media);
        if ($labels !== []) {
            throw ValidationException::withMessages([
                'media' => 'This media item is currently used as: '.implode(', ', $labels).'. Remove that reference before deleting it.',
            ]);
        }
    }
}
