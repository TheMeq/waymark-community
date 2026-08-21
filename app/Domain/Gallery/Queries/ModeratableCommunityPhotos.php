<?php

namespace App\Domain\Gallery\Queries;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class ModeratableCommunityPhotos
{
    /** @return Builder<CommunityPhoto> */
    public function for(User $actor, array $statuses = ['pending']): Builder
    {
        $query = CommunityPhoto::query()
            ->with(['event:id,title,organiser_id', 'specialAlbum:id,title', 'uploader:id,name,display_name'])
            ->whereIn('moderation_status', $statuses)
            ->orderBy('created_at')
            ->orderBy('id');

        if ($actor->hasCapability(ModuleCapability::ModerateAllCommunityPhotos)) {
            return $query;
        }

        if (! $actor->hasCapability(ModuleCapability::ModerateOwnEventPhotos)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('event', fn (Builder $events): Builder => $events->where('organiser_id', $actor->id));
    }
}
