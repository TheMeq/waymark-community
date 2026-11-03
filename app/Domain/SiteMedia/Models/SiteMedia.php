<?php

namespace App\Domain\SiteMedia\Models;

use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\SiteMedia\Data\SiteMediaStorageReference;
use App\Domain\SiteMedia\Enums\SiteMediaPurpose;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['created_by_user_id', 'source_community_photo_id', 'storage_key', 'storage_disk', 'processed_variants', 'mime_type', 'width', 'height', 'file_size_bytes', 'alt_text', 'is_decorative', 'focal_point_x', 'focal_point_y', 'processing_status', 'health_status', 'purpose', 'orphaned_at', 'regeneration_cleanup_status', 'regeneration_cleanup_storage_disk', 'regeneration_cleanup_storage_key'])]
final class SiteMedia extends Model
{
    protected static function booted(): void
    {
        self::saving(function (self $media): void {
            $media->purpose ??= SiteMediaPurpose::Library;

            $requiredDecorativeState = $media->purpose->requiredDecorativeState();
            if ($requiredDecorativeState !== null) {
                $media->is_decorative = $requiredDecorativeState;
            }
            if (! $media->is_decorative && trim((string) $media->alt_text) === '') {
                throw new \LogicException('Site media requires meaningful alt text unless marked decorative.');
            }
            if ($media->is_decorative) {
                $media->alt_text = null;
            }
            if ((float) $media->focal_point_x < 0 || (float) $media->focal_point_x > 1 || (float) $media->focal_point_y < 0 || (float) $media->focal_point_y > 1) {
                throw new \LogicException('Site media focal points must be within the image.');
            }
            if ($media->processing_status === 'complete' && $media->health_status === 'healthy') {
                foreach ((array) $media->processed_variants as $path) {
                    if (! is_string($path) || ! SiteMediaStorageReference::isSafe((string) $media->storage_disk, $path)) {
                        throw new \InvalidArgumentException('Healthy site media must use generated site-media paths on the configured private disk.');
                    }
                }
            }
        });
    }

    protected function casts(): array
    {
        return ['processed_variants' => 'array', 'is_decorative' => 'boolean', 'width' => 'integer', 'height' => 'integer', 'file_size_bytes' => 'integer', 'focal_point_x' => 'decimal:4', 'focal_point_y' => 'decimal:4', 'purpose' => SiteMediaPurpose::class, 'orphaned_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<CommunityPhoto, $this> */
    public function sourceCommunityPhoto(): BelongsTo
    {
        return $this->belongsTo(CommunityPhoto::class);
    }

    /** @return HasMany<SiteMediaAudit, $this> */
    public function audits(): HasMany
    {
        return $this->hasMany(SiteMediaAudit::class);
    }
}
