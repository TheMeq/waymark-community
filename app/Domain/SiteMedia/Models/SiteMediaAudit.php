<?php

namespace App\Domain\SiteMedia\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SiteMediaAudit extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'context' => 'array'];
    }

    /** @return BelongsTo<SiteMedia, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(SiteMedia::class, 'site_media_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
