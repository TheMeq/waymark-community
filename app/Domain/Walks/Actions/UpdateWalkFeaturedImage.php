<?php

namespace App\Domain\Walks\Actions;

use App\Domain\SiteMedia\Actions\CreateSiteMedia;
use App\Domain\SiteMedia\Actions\DiscardUnattachedSiteMedia;
use App\Domain\SiteMedia\Actions\MarkSiteMediaOrphaned;
use App\Domain\SiteMedia\Data\SiteMediaMetadata;
use App\Domain\SiteMedia\Enums\ManagedImageSource;
use App\Domain\SiteMedia\Enums\SiteMediaPurpose;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Domain\SiteMedia\Models\SiteMediaAudit;
use App\Domain\Walks\Data\WalkFeaturedImageInput;
use App\Domain\Walks\Models\Walk;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class UpdateWalkFeaturedImage
{
    public function __construct(
        private CreateSiteMedia $createMedia,
        private DiscardUnattachedSiteMedia $discardMedia,
        private MarkSiteMediaOrphaned $markOrphaned,
    ) {}

    public function handle(User $actor, Walk $walk, WalkFeaturedImageInput $input): Walk
    {
        $authorizableWalk = Walk::query()->with('event')->findOrFail($walk->id);
        Gate::forUser($actor)->authorize('update', $authorizableWalk);

        $createdMedia = null;

        if ($input->source === ManagedImageSource::Managed && $input->upload !== null) {
            $createdMedia = $this->createMedia->handle(
                $actor,
                $input->upload,
                new SiteMediaMetadata($input->altText, false),
                SiteMediaPurpose::WalkFeaturedImage,
            );
        }

        try {
            [$updated, $formerMedia] = DB::transaction(function () use ($actor, $walk, $input, $createdMedia): array {
                $lockedWalk = Walk::query()->lockForUpdate()->findOrFail($walk->id);
                $lockedWalk->setRelation('event', $lockedWalk->event()->lockForUpdate()->firstOrFail());
                Gate::forUser($actor)->authorize('update', $lockedWalk);

                $formerMedia = $lockedWalk->featured_image_media_id === null
                    ? null
                    : SiteMedia::query()->lockForUpdate()->findOrFail($lockedWalk->featured_image_media_id);
                $attachedMedia = $createdMedia === null
                    ? null
                    : SiteMedia::query()->lockForUpdate()->findOrFail($createdMedia->id);

                if ($attachedMedia !== null) {
                    $this->assertAttachable($actor, $attachedMedia);
                }

                $nextMediaId = $lockedWalk->featured_image_media_id;
                $nextPath = $lockedWalk->featured_image_path;
                $nextAlt = $input->altText;

                if ($input->source === ManagedImageSource::Managed) {
                    if ($attachedMedia !== null) {
                        $nextMediaId = $attachedMedia->id;
                    } elseif ($nextMediaId === null) {
                        throw ValidationException::withMessages([
                            'featured_image_upload' => 'Choose a managed image to upload.',
                        ]);
                    }
                } elseif ($input->source === ManagedImageSource::External) {
                    $nextMediaId = null;
                    $nextPath = $input->externalUrl;
                } else {
                    $nextMediaId = null;
                    $nextPath = null;
                    $nextAlt = null;
                }

                $lockedWalk->forceFill([
                    'featured_image_media_id' => $nextMediaId,
                    'featured_image_path' => $nextPath,
                    'featured_image_alt_text' => $nextAlt,
                ])->save();

                $context = $this->auditContext($lockedWalk);

                if ($attachedMedia !== null) {
                    $attachedMedia->forceFill(['orphaned_at' => null])->save();
                    $this->audit($actor, $attachedMedia, 'attached', $context);
                }

                if ($formerMedia !== null && $formerMedia->id !== $nextMediaId) {
                    $this->audit($actor, $formerMedia, 'detached', $context);
                } else {
                    $formerMedia = null;
                }

                return [$lockedWalk->refresh()->load(['event', 'featuredMedia']), $formerMedia];
            });
        } catch (\Throwable $exception) {
            if ($createdMedia !== null) {
                try {
                    $this->discardMedia->handle($actor, $createdMedia);
                } catch (\Throwable) {
                    // The purpose-bound record remains an orphan candidate for retryable cleanup.
                }
            }

            throw $exception;
        }

        if ($formerMedia !== null && $this->markOrphaned->handle($formerMedia)) {
            $this->audit($actor, $formerMedia->fresh(), 'orphaned', $this->auditContext($updated));
        }

        return $updated;
    }

    private function assertAttachable(User $actor, SiteMedia $media): void
    {
        if ($media->purpose !== SiteMediaPurpose::WalkFeaturedImage
            || (int) $media->created_by_user_id !== (int) $actor->id
            || $media->processing_status !== 'complete'
            || $media->health_status !== 'healthy') {
            throw ValidationException::withMessages([
                'featured_image_upload' => 'The processed Walk image could not be attached safely.',
            ]);
        }
    }

    /** @return array{owner_type: string, owner_id: int, slot: string} */
    private function auditContext(Walk $walk): array
    {
        return [
            'owner_type' => 'walk',
            'owner_id' => (int) $walk->id,
            'slot' => 'featured_image',
        ];
    }

    /** @param array<string, mixed> $context */
    private function audit(User $actor, SiteMedia $media, string $action, array $context): void
    {
        SiteMediaAudit::query()->create([
            'site_media_id' => $media->id,
            'actor_user_id' => $actor->id,
            'action' => $action,
            'before' => null,
            'after' => null,
            'context' => $context,
        ]);
    }
}
