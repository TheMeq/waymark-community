<?php

namespace App\Domain\Gallery\Actions;

use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\Models\CommunityPhotoModerationAudit;
use App\Domain\Gallery\Models\CommunityPhotoRemovalRequest;
use App\Domain\Gallery\Queries\ModeratableCommunityPhotoRemovalRequests;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ResolveCommunityPhotoRemovalRequest
{
    public function removePhoto(User $actor, CommunityPhotoRemovalRequest $request): void
    {
        DB::transaction(function () use ($actor, $request): void {
            $locked = CommunityPhotoRemovalRequest::query()->lockForUpdate()->findOrFail($request->id);
            if (! app(ModeratableCommunityPhotoRemovalRequests::class)->for($actor, ['open'])->whereKey($locked->id)->exists()) {
                throw new AuthorizationException;
            }
            $photo = CommunityPhoto::query()->findOrFail($locked->community_photo_id);
            app(ModerateCommunityPhoto::class)->remove($actor, $photo);
            $locked->update(['status' => 'reviewed', 'resolved_by_user_id' => $actor->id, 'resolved_at' => now()]);
            CommunityPhotoModerationAudit::query()->create(['community_photo_id' => $locked->community_photo_id, 'actor_user_id' => $actor->id, 'action' => 'removal_request_removed_photo', 'context' => ['removal_request_id' => $locked->id, 'outcome' => 'removed']]);
        });
    }

    public function handle(User $actor, CommunityPhotoRemovalRequest $request, string $status): CommunityPhotoRemovalRequest
    {
        if (! in_array($status, ['reviewed', 'dismissed'], true)) {
            throw ValidationException::withMessages(['status' => 'Choose a removal outcome.']);
        }

        return DB::transaction(function () use ($actor, $request, $status): CommunityPhotoRemovalRequest {
            $locked = CommunityPhotoRemovalRequest::query()->lockForUpdate()->findOrFail($request->id);
            if (! app(ModeratableCommunityPhotoRemovalRequests::class)->for($actor, ['open', 'reviewed', 'dismissed'])->whereKey($locked->id)->exists()) {
                throw new AuthorizationException;
            }
            if ($locked->status === 'open') {
                $locked->update(['status' => $status, 'resolved_by_user_id' => $actor->id, 'resolved_at' => now()]);
                CommunityPhotoModerationAudit::query()->create(['community_photo_id' => $locked->community_photo_id, 'actor_user_id' => $actor->id, 'action' => 'removal_request_'.$status, 'context' => ['removal_request_id' => $locked->id, 'outcome' => $status]]);
            } elseif ($locked->status !== $status) {
                throw ValidationException::withMessages(['request' => 'This removal request has already been resolved.']);
            }

            return $locked;
        });
    }
}
