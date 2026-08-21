<?php

namespace App\Domain\Gallery\Actions;

use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\Models\CommunityPhotoRemovalRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RequestCommunityPhotoRemoval
{
    public function handle(User $actor, CommunityPhoto $photo, ?string $detail = null): CommunityPhotoRemovalRequest
    {
        return DB::transaction(function () use ($actor, $photo, $detail): CommunityPhotoRemovalRequest {
            $photo = CommunityPhoto::query()->lockForUpdate()->findOrFail($photo->id);
            if ($photo->uploader_id !== $actor->id) {
                throw new AuthorizationException;
            }
            if ($photo->moderation_status !== 'approved' || $photo->published_at === null) {
                throw ValidationException::withMessages(['photo' => 'Only published photos can be requested for removal.']);
            }
            $detail = $detail === null ? null : trim($detail);
            if ($detail !== null && mb_strlen($detail) > 1000) {
                throw ValidationException::withMessages(['detail' => 'This value is too long.']);
            }

            return CommunityPhotoRemovalRequest::query()->firstOrCreate([
                'community_photo_id' => $photo->id, 'requester_user_id' => $actor->id, 'status' => 'open',
            ], ['detail' => $detail === '' ? null : $detail, 'context_snapshot' => ['photo_id' => $photo->id, 'event_id' => $photo->event_id, 'special_album_id' => $photo->special_album_id, 'caption' => $photo->caption]]);
        });
    }
}
