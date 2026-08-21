<?php

namespace App\Domain\Gallery\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CommunityPhotoReport extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['context_snapshot' => 'array', 'resolved_at' => 'datetime'];
    }

    /** @return BelongsTo<CommunityPhoto, $this> */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(CommunityPhoto::class, 'community_photo_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }
}
