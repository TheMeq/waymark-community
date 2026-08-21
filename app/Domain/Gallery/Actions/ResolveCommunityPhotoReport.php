<?php

namespace App\Domain\Gallery\Actions;

use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\Models\CommunityPhotoModerationAudit;
use App\Domain\Gallery\Models\CommunityPhotoReport;
use App\Domain\Gallery\Queries\ModeratableCommunityPhotoReports;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ResolveCommunityPhotoReport
{
    public function removePhoto(User $actor, CommunityPhotoReport $report): void
    {
        DB::transaction(function () use ($actor, $report): void {
            $locked = CommunityPhotoReport::query()->lockForUpdate()->findOrFail($report->id);
            if (! app(ModeratableCommunityPhotoReports::class)->for($actor, ['open'])->whereKey($locked->id)->exists()) {
                throw new AuthorizationException;
            }
            $photo = CommunityPhoto::query()->findOrFail($locked->community_photo_id);
            app(ModerateCommunityPhoto::class)->remove($actor, $photo);
            $locked->update(['status' => 'reviewed', 'resolved_by_user_id' => $actor->id, 'resolved_at' => now()]);
            CommunityPhotoModerationAudit::query()->create(['community_photo_id' => $locked->community_photo_id, 'actor_user_id' => $actor->id, 'action' => 'report_removed_photo', 'context' => ['report_id' => $locked->id, 'reason' => $locked->reason, 'outcome' => 'removed']]);
        });
    }

    public function handle(User $actor, CommunityPhotoReport $report, string $status): CommunityPhotoReport
    {
        if (! in_array($status, ['reviewed', 'dismissed'], true)) {
            throw ValidationException::withMessages(['status' => 'Choose a report outcome.']);
        }

        return DB::transaction(function () use ($actor, $report, $status): CommunityPhotoReport {
            $locked = CommunityPhotoReport::query()->lockForUpdate()->findOrFail($report->id);
            if (! app(ModeratableCommunityPhotoReports::class)->for($actor, ['open', 'reviewed', 'dismissed'])->whereKey($locked->id)->exists()) {
                throw new AuthorizationException;
            }
            if ($locked->status === 'open') {
                $locked->update(['status' => $status, 'resolved_by_user_id' => $actor->id, 'resolved_at' => now()]);
                CommunityPhotoModerationAudit::query()->create(['community_photo_id' => $locked->community_photo_id, 'actor_user_id' => $actor->id, 'action' => 'report_'.$status, 'context' => ['report_id' => $locked->id, 'reason' => $locked->reason, 'outcome' => $status]]);
            } elseif ($locked->status !== $status) {
                throw ValidationException::withMessages(['report' => 'This report has already been resolved.']);
            }

            return $locked;
        });
    }
}
