<?php

namespace App\Domain\Gallery\Actions;

use App\Domain\Gallery\Models\CommunityPhotoRemovalRequest;
use App\Domain\Gallery\Queries\ModeratableCommunityPhotoRemovalRequests;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ResolveCommunityPhotoRemovalRequest
{
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
            } elseif ($locked->status !== $status) {
                throw ValidationException::withMessages(['request' => 'This removal request has already been resolved.']);
            }

            return $locked;
        });
    }
}
