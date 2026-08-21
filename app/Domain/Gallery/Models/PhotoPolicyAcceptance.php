<?php

namespace App\Domain\Gallery\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'policy_version', 'accepted_at'])]
final class PhotoPolicyAcceptance extends Model
{
    /** @return BelongsTo<User, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }
}
