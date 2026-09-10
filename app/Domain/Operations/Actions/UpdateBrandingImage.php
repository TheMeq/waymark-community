<?php

namespace App\Domain\Operations\Actions;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Operations\Data\BrandingImageInput;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\SiteMedia\Actions\CreateSiteMedia;
use App\Domain\SiteMedia\Actions\DiscardUnattachedSiteMedia;
use App\Domain\SiteMedia\Actions\MarkSiteMediaOrphaned;
use App\Domain\SiteMedia\Data\SiteMediaMetadata;
use App\Domain\SiteMedia\Enums\ManagedImageSource;
use App\Domain\SiteMedia\Enums\SiteMediaPurpose;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Domain\SiteMedia\Models\SiteMediaAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateBrandingImage
{
    public function __construct(
        private CreateSiteMedia $createMedia,
        private DiscardUnattachedSiteMedia $discardMedia,
        private MarkSiteMediaOrphaned $markOrphaned,
    ) {}

    public function handle(
        User $actor,
        SiteProfile $profile,
        SiteMediaPurpose $purpose,
        BrandingImageInput $input,
    ): SiteProfile {
        $this->authorize($actor);
        $slot = $this->slotFor($purpose, $input);
        $createdMedia = null;

        if ($input->source === ManagedImageSource::Managed && $input->upload !== null) {
            $createdMedia = $this->createMedia->handle(
                $actor,
                $input->upload,
                new SiteMediaMetadata(null, true),
                $purpose,
            );
        }

        try {
            [$updated, $formerMedia] = DB::transaction(function () use ($actor, $profile, $purpose, $input, $slot, $createdMedia): array {
                if ((int) $profile->getKey() !== SiteProfile::SINGLETON_ID) {
                    throw ValidationException::withMessages(['branding' => 'The current site profile could not be updated.']);
                }

                $lockedProfile = SiteProfile::query()->lockForUpdate()->findOrFail(SiteProfile::SINGLETON_ID);
                $this->authorize($actor);
                $mediaColumn = $slot.'_media_id';
                $pathColumn = $slot.'_path';
                $formerMedia = $lockedProfile->{$mediaColumn} === null
                    ? null
                    : SiteMedia::query()->lockForUpdate()->findOrFail($lockedProfile->{$mediaColumn});
                $attachedMedia = $createdMedia === null
                    ? null
                    : SiteMedia::query()->lockForUpdate()->findOrFail($createdMedia->id);

                if ($attachedMedia !== null) {
                    $this->assertAttachable($actor, $attachedMedia, $purpose, $slot);
                }

                $nextMediaId = $lockedProfile->{$mediaColumn};
                $nextPath = $lockedProfile->{$pathColumn};

                if ($input->source === ManagedImageSource::Managed) {
                    if ($attachedMedia !== null) {
                        $nextMediaId = $attachedMedia->id;
                    } elseif ($nextMediaId === null) {
                        throw ValidationException::withMessages([
                            $slot.'_upload' => 'Choose a managed image to upload.',
                        ]);
                    }
                } elseif ($input->source === ManagedImageSource::External) {
                    $nextMediaId = null;
                    $nextPath = $input->externalUrl;
                } else {
                    $hadManagedMedia = $nextMediaId !== null;
                    $nextMediaId = null;

                    if (! $hadManagedMedia || $input->removeFallback) {
                        $nextPath = null;
                    }
                }

                $lockedProfile->forceFill([
                    $mediaColumn => $nextMediaId,
                    $pathColumn => $nextPath,
                ])->save();

                $context = $this->auditContext($slot);

                if ($attachedMedia !== null) {
                    $attachedMedia->forceFill(['orphaned_at' => null])->save();
                    $this->audit($actor, $attachedMedia, 'attached', $context);
                }

                if ($formerMedia !== null && $formerMedia->id !== $nextMediaId) {
                    $this->audit($actor, $formerMedia, 'detached', $context);
                } else {
                    $formerMedia = null;
                }

                return [$lockedProfile->refresh()->load(['logoMedia', 'faviconMedia']), $formerMedia];
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
            $this->audit($actor, $formerMedia->fresh(), 'orphaned', $this->auditContext($slot));
        }

        return $updated;
    }

    private function authorize(User $actor): void
    {
        if (! $actor->hasCapability(ModuleCapability::ManageContent)) {
            throw ValidationException::withMessages(['branding' => 'You are not allowed to manage branding images.']);
        }
    }

    private function slotFor(SiteMediaPurpose $purpose, BrandingImageInput $input): string
    {
        if ($purpose !== $input->purpose) {
            throw ValidationException::withMessages(['branding' => 'The branding image purpose does not match the requested change.']);
        }

        return match ($purpose) {
            SiteMediaPurpose::SiteLogo => 'logo',
            SiteMediaPurpose::SiteFavicon => 'favicon',
            default => throw ValidationException::withMessages([
                'branding' => 'Branding images must use the logo or favicon purpose.',
            ]),
        };
    }

    private function assertAttachable(User $actor, SiteMedia $media, SiteMediaPurpose $purpose, string $slot): void
    {
        if ($media->purpose !== $purpose
            || (int) $media->created_by_user_id !== (int) $actor->id
            || $media->processing_status !== 'complete'
            || $media->health_status !== 'healthy') {
            throw ValidationException::withMessages([
                $slot.'_upload' => 'The processed branding image could not be attached safely.',
            ]);
        }
    }

    /** @return array{owner_type: string, owner_id: int, slot: string} */
    private function auditContext(string $slot): array
    {
        return [
            'owner_type' => 'site_profile',
            'owner_id' => SiteProfile::SINGLETON_ID,
            'slot' => $slot,
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
