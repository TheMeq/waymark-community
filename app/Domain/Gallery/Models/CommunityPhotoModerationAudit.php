<?php

namespace App\Domain\Gallery\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['community_photo_id', 'actor_user_id', 'action', 'before', 'after', 'context'])]
final class CommunityPhotoModerationAudit extends Model
{
    /** @return BelongsTo<CommunityPhoto, $this> */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(CommunityPhoto::class, 'community_photo_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'context' => 'array'];
    }
}
