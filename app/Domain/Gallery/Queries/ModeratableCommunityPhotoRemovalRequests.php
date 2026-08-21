<?php

namespace App\Domain\Gallery\Queries;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Gallery\Models\CommunityPhotoRemovalRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class ModeratableCommunityPhotoRemovalRequests
{
    /** @return Builder<CommunityPhotoRemovalRequest> */
    public function for(User $actor, array $statuses = ['open']): Builder
    {
        $query = CommunityPhotoRemovalRequest::query()->with(['photo.event:id,title,organiser_id', 'photo.specialAlbum:id,title', 'requester:id,name,display_name'])
            ->whereIn('status', $statuses)->orderBy('created_at')->orderBy('id');
        if ($actor->hasCapability(ModuleCapability::ModerateAllCommunityPhotos)) {
            return $query;
        }
        if (! $actor->hasCapability(ModuleCapability::ModerateOwnEventPhotos)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('photo.event', fn (Builder $events): Builder => $events->where('organiser_id', $actor->id));
    }
}
