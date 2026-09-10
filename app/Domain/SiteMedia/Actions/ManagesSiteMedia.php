<?php

namespace App\Domain\SiteMedia\Actions;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\SiteMedia\Data\SiteMediaMetadata;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Domain\SiteMedia\Models\SiteMediaAudit;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

trait ManagesSiteMedia
{
    private function authorizeSiteMedia(User $actor): void
    {
        if (! $actor->hasCapability(ModuleCapability::ManageSiteMedia)) {
            throw new AuthorizationException;
        }
    }

    private function validateMetadata(SiteMediaMetadata $metadata): array
    {
        $alt = trim((string) $metadata->altText);
        if (! $metadata->isDecorative && $alt === '') {
            throw ValidationException::withMessages(['alt_text' => 'Describe the image or mark it decorative.']);
        }
        if (mb_strlen($alt) > 2000) {
            throw ValidationException::withMessages(['alt_text' => 'Alt text is too long.']);
        }
        if ($metadata->focalPointX < 0 || $metadata->focalPointX > 1 || $metadata->focalPointY < 0 || $metadata->focalPointY > 1) {
            throw ValidationException::withMessages(['focal_point' => 'Focal point must be within the image.']);
        }

        return ['alt_text' => $metadata->isDecorative ? null : $alt, 'is_decorative' => $metadata->isDecorative, 'focal_point_x' => $metadata->focalPointX, 'focal_point_y' => $metadata->focalPointY];
    }

    private function audit(User $actor, SiteMedia $media, string $action, array $before = [], array $after = [], array $context = []): void
    {
        SiteMediaAudit::query()->create(['site_media_id' => $media->id, 'actor_user_id' => $actor->id, 'action' => $action, 'before' => $before ?: null, 'after' => $after ?: null, 'context' => $context ?: null]);
    }

    private function snapshot(SiteMedia $media): array
    {
        return $media->only(['alt_text', 'is_decorative', 'focal_point_x', 'focal_point_y', 'health_status', 'purpose', 'orphaned_at', 'processed_variants', 'regeneration_cleanup_status', 'regeneration_cleanup_storage_disk', 'regeneration_cleanup_storage_key']);
    }
}
