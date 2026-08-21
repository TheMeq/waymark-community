<?php

namespace App\Domain\Gallery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CommunityPhotoProcessingJob extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'available_at' => 'datetime',
            'claimed_at' => 'datetime',
            'lease_expires_at' => 'datetime',
            'staging_lease_expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CommunityPhoto, $this> */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(CommunityPhoto::class, 'community_photo_id');
    }
}
