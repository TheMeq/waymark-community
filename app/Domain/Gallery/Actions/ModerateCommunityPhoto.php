<?php

namespace App\Domain\Gallery\Actions;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Events\Models\Event;
use App\Domain\Gallery\Data\CommunityPhotoModerationRequest;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\Models\CommunityPhotoModerationAudit;
use App\Domain\Gallery\Models\SpecialAlbum;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ModerateCommunityPhoto
{
    public function approve(User $actor, CommunityPhoto $photo): CommunityPhoto
    {
        return $this->mutate($actor, $photo, 'approved', function (CommunityPhoto $locked): void {
            $this->assertProcessed($locked);
            if ($locked->moderation_status === 'approved') {
                return;
            }
            $locked->forceFill(['moderation_status' => 'approved', 'published_at' => now()])->save();
        });
    }

    public function reject(User $actor, CommunityPhoto $photo): CommunityPhoto
    {
        return $this->mutate($actor, $photo, 'rejected', function (CommunityPhoto $locked): void {
            if ($locked->moderation_status === 'rejected') {
                return;
            }
            $locked->forceFill(['moderation_status' => 'rejected', 'published_at' => null, 'is_featured' => false])->save();
        });
    }

    public function remove(User $actor, CommunityPhoto $photo): CommunityPhoto
    {
        return $this->mutate($actor, $photo, 'removed', function (CommunityPhoto $locked): void {
            if ($locked->moderation_status !== 'approved') {
                throw ValidationException::withMessages(['photo' => 'Only published photos can be removed.']);
            }
            $locked->forceFill(['moderation_status' => 'removed', 'published_at' => null, 'is_featured' => false])->save();
        });
    }

    public function edit(User $actor, CommunityPhoto $photo, CommunityPhotoModerationRequest $request): CommunityPhoto
    {
        return $this->mutate($actor, $photo, 'edited', function (CommunityPhoto $locked) use ($request): void {
            $caption = $this->nullableText($request->caption, 2000, 'caption');
            $photographer = $this->nullableText($request->photographerName, 255, 'photographer_name');
            $locked->forceFill(['caption' => $caption, 'photographer_name' => $photographer])->save();
        });
    }

    public function move(User $actor, CommunityPhoto $photo, string $target): CommunityPhoto
    {
        return $this->mutate($actor, $photo, 'moved', function (CommunityPhoto $locked) use ($actor, $target): void {
            [$event, $album] = $this->target($target);
            $this->assertTargetScope($actor, $event, $album);
            $locked->forceFill(['event_id' => $event?->id, 'special_album_id' => $album?->id])->save();
        });
    }

    public function rotate(User $actor, CommunityPhoto $photo, int $degrees): CommunityPhoto
    {
        return $this->mutate($actor, $photo, 'rotated', function (CommunityPhoto $locked) use ($degrees): void {
            $this->assertProcessed($locked);
            if (! in_array($degrees, [90, 180, 270], true)) {
                throw ValidationException::withMessages(['rotation' => 'Rotation must be a quarter turn.']);
            }
            $locked->forceFill(['presentation_rotation' => ((int) $locked->presentation_rotation + $degrees) % 360])->save();
        });
    }

    public function feature(User $actor, CommunityPhoto $photo): CommunityPhoto
    {
        return DB::transaction(function () use ($actor, $photo): CommunityPhoto {
            $locked = CommunityPhoto::query()->lockForUpdate()->findOrFail($photo->id);
            $this->authorize($actor, $locked);
            $this->assertProcessed($locked);
            if ($locked->moderation_status !== 'approved') {
                throw ValidationException::withMessages(['photo' => 'Only approved photos can be featured.']);
            }
            $before = $this->snapshot($locked);
            $context = $locked->event_id !== null ? ['event_id' => $locked->event_id] : ['special_album_id' => $locked->special_album_id];
            CommunityPhoto::query()->where($context)->whereKeyNot($locked->id)->where('is_featured', true)->update(['is_featured' => false]);
            $locked->forceFill(['is_featured' => true])->save();
            $this->audit($actor, $locked, 'featured', $before, $this->snapshot($locked));

            return $locked;
        });
    }

    /** @param array<int, int> $photoIds */
    public function bulkApprove(User $actor, array $photoIds): int
    {
        return $this->bulk($actor, $photoIds, 'approved');
    }

    /** @param array<int, int> $photoIds */
    public function bulkReject(User $actor, array $photoIds): int
    {
        return $this->bulk($actor, $photoIds, 'rejected');
    }

    private function mutate(User $actor, CommunityPhoto $photo, string $action, \Closure $mutation): CommunityPhoto
    {
        return DB::transaction(function () use ($actor, $photo, $action, $mutation): CommunityPhoto {
            $locked = CommunityPhoto::query()->lockForUpdate()->findOrFail($photo->id);
            $this->authorize($actor, $locked);
            $before = $this->snapshot($locked);
            $mutation($locked);
            $this->audit($actor, $locked, $action, $before, $this->snapshot($locked));

            return $locked;
        });
    }

    /** @param array<int, int> $photoIds */
    private function bulk(User $actor, array $photoIds, string $status): int
    {
        $ids = array_values(array_unique(array_filter($photoIds, 'is_int')));
        if ($ids === []) {
            return 0;
        }

        return DB::transaction(function () use ($actor, $ids, $status): int {
            $photos = CommunityPhoto::query()->whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');
            if ($photos->count() !== count($ids)) {
                throw new AuthorizationException;
            }
            foreach ($ids as $id) {
                $this->authorize($actor, $photos[$id]);
                if ($status === 'approved') {
                    $this->assertProcessed($photos[$id]);
                }
            }
            foreach ($ids as $id) {
                $photo = $photos[$id];
                $before = $this->snapshot($photo);
                $photo->forceFill($status === 'approved'
                    ? ['moderation_status' => 'approved', 'published_at' => now()]
                    : ['moderation_status' => 'rejected', 'published_at' => null, 'is_featured' => false])->save();
                $this->audit($actor, $photo, 'bulk_'.$status, $before, $this->snapshot($photo));
            }

            return count($ids);
        });
    }

    private function authorize(User $actor, CommunityPhoto $photo): void
    {
        if ($actor->hasCapability(ModuleCapability::ModerateAllCommunityPhotos)) {
            return;
        }
        if ($actor->hasCapability(ModuleCapability::ModerateOwnEventPhotos)
            && $photo->event?->organiser_id === $actor->id) {
            return;
        }
        throw new AuthorizationException;
    }

    private function assertTargetScope(User $actor, ?Event $event, ?SpecialAlbum $album): void
    {
        if ($actor->hasCapability(ModuleCapability::ModerateAllCommunityPhotos)) {
            return;
        }
        if ($event instanceof Event && $event->organiser_id === $actor->id) {
            return;
        }
        throw new AuthorizationException;
    }

    private function assertProcessed(CommunityPhoto $photo): void
    {
        if ($photo->processing_status !== 'complete' || ! is_array($photo->processed_variants) || $photo->processed_variants === []) {
            throw ValidationException::withMessages(['photo' => 'Only safely processed photos can be approved or featured.']);
        }
    }

    /** @return array{?Event, ?SpecialAlbum} */
    private function target(string $target): array
    {
        if (preg_match('/^event:(\d+)$/', $target, $matches) === 1) {
            return [Event::query()->findOrFail($matches[1]), null];
        }
        if (preg_match('/^album:(\d+)$/', $target, $matches) === 1) {
            return [null, SpecialAlbum::query()->findOrFail($matches[1])];
        }
        throw ValidationException::withMessages(['context' => 'Choose an eligible event or special album.']);
    }

    private function nullableText(?string $value, int $maximum, string $field): ?string
    {
        $value = $value === null ? null : trim($value);
        if ($value !== null && mb_strlen($value) > $maximum) {
            throw ValidationException::withMessages([$field => 'This value is too long.']);
        }

        return $value === '' ? null : $value;
    }

    /** @return array<string, mixed> */
    private function snapshot(CommunityPhoto $photo): array
    {
        return $photo->only(['event_id', 'special_album_id', 'caption', 'photographer_name', 'moderation_status', 'published_at', 'is_featured', 'presentation_rotation']);
    }

    /** @param array<string, mixed> $before @param array<string, mixed> $after */
    private function audit(User $actor, CommunityPhoto $photo, string $action, array $before, array $after): void
    {
        CommunityPhotoModerationAudit::query()->create([
            'community_photo_id' => $photo->id,
            'actor_user_id' => $actor->id,
            'action' => $action,
            'before' => $before,
            'after' => $after,
            'context' => [],
        ]);
    }
}
