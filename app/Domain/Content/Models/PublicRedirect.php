<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Support\PublicRedirectGuard;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['source_path', 'target_url', 'status_code', 'enabled', 'automatic', 'created_by_user_id'])]
final class PublicRedirect extends Model
{
    protected static function booted(): void
    {
        self::saving(fn (self $redirect) => app(PublicRedirectGuard::class)->validate($redirect));
    }

    protected function casts(): array
    {
        return ['status_code' => 'integer', 'enabled' => 'boolean', 'automatic' => 'boolean'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
