<?php

namespace App\Domain\Gallery\Actions;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Events\Models\Event;
use App\Domain\Gallery\CommunityPhotoModerationPreviewResolver;
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
    public function __construct(private readonly CommunityPhotoModerationPreviewResolver $readiness) {}

    public function approve(User $actor, CommunityPhoto $photo): CommunityPhoto
    {
        return $this->mutate($actor, $photo, 'approved', function (CommunityPhoto $locked) use ($actor): bool {
            if ($locked->moderation_status !== 'pending') {
                if ($locked->moderation_status === 'approved') {
                    return false;
                }
                throw ValidationException::withMessages(['photo' => 'Only pending photos can be approved.']);
            }
            $this->assertReady($actor, $locked);
            $locked->forceFill(['moderation_status' => 'approved', 'published_at' => now()])->save();

            return true;
        });
    }

    public function reject(User $actor, CommunityPhoto $photo): CommunityPhoto
    {
        return $this->mutate($actor, $photo, 'rejected', function (CommunityPhoto $locked) use ($actor): bool {
            if ($locked->moderation_status !== 'pending') {
                if ($locked->moderation_status === 'rejected') {
                    return false;
                }
                throw ValidationException::withMessages(['photo' => 'Only pending photos can be rejected.']);
            }
            $this->assertReady($actor, $locked);
            $locked->forceFill(['moderation_status' => 'rejected', 'published_at' => null, 'is_featured' => false])->save();

            return true;
        });
    }

    public function remove(User $actor, CommunityPhoto $photo): CommunityPhoto
    {
        return $this->mutate($actor, $photo, 'removed', function (CommunityPhoto $locked): bool {
            if ($locked->moderation_status === 'removed') {
                return false;
            }
            if ($locked->moderation_status !== 'approved') {
                throw ValidationException::withMessages(['photo' => 'Only published photos can be removed.']);
            }
            $locked->forceFill(['moderation_status' => 'removed', 'published_at' => null, 'is_featured' => false])->save();

            return true;
        });
    }

    public function edit(User $actor, CommunityPhoto $photo, CommunityPhotoModerationRequest $request): CommunityPhoto
    {
        return $this->mutate($actor, $photo, 'edited', function (CommunityPhoto $locked) use ($request): bool {
            $this->assertEditable($locked);
            $caption = $this->nullableText($request->caption, 2000, 'caption');
            $photographer = $this->nullableText($request->photographerName, 255, 'photographer_name');
            if ($locked->caption === $caption && $locked->photographer_name === $photographer) {
                return false;
            }
            $locked->forceFill(['caption' => $caption, 'photographer_name' => $photographer])->save();

            return true;
        });
    }

    public function move(User $actor, CommunityPhoto $photo, string $target): CommunityPhoto
    {
        return $this->mutate($actor, $photo, 'moved', function (CommunityPhoto $locked) use ($actor, $target): bool {
            $this->assertEditable($locked);
            [$event, $album] = $this->target($target);
            $this->assertTargetScope($actor, $event, $album);
            if ($locked->event_id === $event?->id && $locked->special_album_id === $album?->id) {
                return false;
            }
            $locked->forceFill(['event_id' => $event?->id, 'special_album_id' => $album?->id, 'is_featured' => false])->save();

            return true;
        }, ['destination' => $target]);
    }

    public function editAndMove(User $actor, CommunityPhoto $photo, CommunityPhotoModerationRequest $request, string $target): CommunityPhoto
    {
        return DB::transaction(function () use ($actor, $photo, $request, $target): CommunityPhoto {
            $locked = CommunityPhoto::query()->lockForUpdate()->findOrFail($photo->id);
            $this->authorize($actor, $locked);
            $this->assertEditable($locked);
            [$event, $album] = $this->target($target);
            $this->assertTargetScope($actor, $event, $album);
            $caption = $this->nullableText($request->caption, 2000, 'caption');
            $photographer = $this->nullableText($request->photographerName, 255, 'photographer_name');
            $before = $this->snapshot($locked);
            $contextChanged = $locked->event_id !== $event?->id || $locked->special_album_id !== $album?->id;
            if (! $contextChanged && $locked->caption === $caption && $locked->photographer_name === $photographer) {
                return $locked;
            }
            $locked->forceFill(['caption' => $caption, 'photographer_name' => $photographer, 'event_id' => $event?->id, 'special_album_id' => $album?->id, 'is_featured' => $contextChanged ? false : $locked->is_featured])->save();
            $this->audit($actor, $locked, 'edited_and_moved', $before, $this->snapshot($locked), ['destination' => $target]);

            return $locked;
        });
    }

    public function rotate(User $actor, CommunityPhoto $photo, int $degrees): CommunityPhoto
    {
        return $this->mutate($actor, $photo, 'rotated', function (CommunityPhoto $locked) use ($degrees): bool {
            $this->assertEditable($locked);
            $this->assertProcessed($locked);
            if (! in_array($degrees, [90, 180, 270], true)) {
                throw ValidationException::withMessages(['rotation' => 'Rotation must be a quarter turn.']);
            }
            $locked->forceFill(['presentation_rotation' => ((int) $locked->presentation_rotation + $degrees) % 360])->save();

            return true;
        }, ['degrees' => $degrees]);
    }

    public function feature(User $actor, CommunityPhoto $photo): CommunityPhoto
    {
        return DB::transaction(function () use ($actor, $photo): CommunityPhoto {
            $candidate = CommunityPhoto::query()->findOrFail($photo->id);
            if ($candidate->event_id !== null) {
                Event::query()->lockForUpdate()->findOrFail($candidate->event_id);
                $context = ['event_id' => $candidate->event_id];
            } else {
                SpecialAlbum::query()->lockForUpdate()->findOrFail($candidate->special_album_id);
                $context = ['special_album_id' => $candidate->special_album_id];
            }
            $contextPhotos = CommunityPhoto::query()->where($context)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $locked = $contextPhotos->get($photo->id);
            if (! $locked instanceof CommunityPhoto) {
                throw ValidationException::withMessages(['photo' => 'The photo context changed. Try again.']);
            }
            $this->authorize($actor, $locked);
            if ($locked->moderation_status !== 'approved') {
                throw ValidationException::withMessages(['photo' => 'Only approved photos can be featured.']);
            }
            if ($locked->is_featured) {
                return $locked;
            }
            $this->assertCurrentPublic($locked);
            $this->assertReady($actor, $locked);
            $before = $this->snapshot($locked);
            $displaced = $contextPhotos->filter(fn (CommunityPhoto $item): bool => $item->id !== $locked->id && $item->is_featured);
            foreach ($displaced as $previous) {
                $previousBefore = $this->snapshot($previous);
                $previous->forceFill(['is_featured' => false])->save();
                $this->audit($actor, $previous, 'feature_displaced', $previousBefore, $this->snapshot($previous));
            }
            $locked->forceFill(['is_featured' => true])->save();
            $this->audit($actor, $locked, 'featured', $before, $this->snapshot($locked));

            return $locked;
        });
    }

    public function unfeature(User $actor, CommunityPhoto $photo): CommunityPhoto
    {
        return $this->mutate($actor, $photo, 'unfeatured', function (CommunityPhoto $locked): bool {
            if (! $locked->is_featured) {
                return false;
            }
            $locked->forceFill(['is_featured' => false])->save();

            return true;
        });
    }

    public function setManualSortOrder(User $actor, CommunityPhoto $photo, ?int $sortOrder): CommunityPhoto
    {
        return $this->mutate($actor, $photo, 'manual_sort_ordered', function (CommunityPhoto $locked) use ($sortOrder): bool {
            $this->assertEditable($locked);
            if ($sortOrder !== null && ($sortOrder < 0 || $sortOrder > 1000000)) {
                throw ValidationException::withMessages(['sort_order' => 'Choose a valid gallery position.']);
            }
            if ($locked->manual_sort_order === $sortOrder) {
                return false;
            }
            $locked->forceFill(['manual_sort_order' => $sortOrder])->save();

            return true;
        }, ['manual_sort_order' => $sortOrder ?? 'automatic']);
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

    /** @param array<string, int|string> $context */
    private function mutate(User $actor, CommunityPhoto $photo, string $action, \Closure $mutation, array $context = []): CommunityPhoto
    {
        return DB::transaction(function () use ($actor, $photo, $action, $mutation, $context): CommunityPhoto {
            $locked = CommunityPhoto::query()->lockForUpdate()->findOrFail($photo->id);
            $this->authorize($actor, $locked);
            $before = $this->snapshot($locked);
            if ($mutation($locked)) {
                $this->audit($actor, $locked, $action, $before, $this->snapshot($locked), $context);
            }

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
                $this->assertReady($actor, $photos[$id]);
                if ($photos[$id]->moderation_status !== 'pending') {
                    throw ValidationException::withMessages(['photo' => 'Bulk moderation accepts pending photos only.']);
                }
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

    private function assertReady(User $actor, CommunityPhoto $photo): void
    {
        if (! $this->readiness->isReady($actor, $photo)) {
            throw ValidationException::withMessages(['photo' => 'Only photos with a safe processed preview can be moderated.']);
        }
    }

    private function assertCurrentPublic(CommunityPhoto $photo): void
    {
        if ($photo->published_at === null || $photo->published_at->isFuture()) {
            throw ValidationException::withMessages(['photo' => 'Only currently public photos can be featured.']);
        }
    }

    private function assertEditable(CommunityPhoto $photo): void
    {
        if (! in_array($photo->moderation_status, ['pending', 'approved'], true)) {
            throw ValidationException::withMessages(['photo' => 'Only pending or approved photos can be edited.']);
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
        return $photo->only(['event_id', 'special_album_id', 'caption', 'photographer_name', 'moderation_status', 'published_at', 'manual_sort_order', 'is_featured', 'presentation_rotation']);
    }

    /** @param array<string, mixed> $before @param array<string, mixed> $after */
    /** @param array<string, int|string> $context */
    private function audit(User $actor, CommunityPhoto $photo, string $action, array $before, array $after, array $context = []): void
    {
        CommunityPhotoModerationAudit::query()->create([
            'community_photo_id' => $photo->id,
            'actor_user_id' => $actor->id,
            'action' => $action,
            'before' => $before,
            'after' => $after,
            'context' => $context,
        ]);
    }
}
