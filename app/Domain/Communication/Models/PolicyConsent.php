<?php

namespace App\Domain\Communication\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'policy_version_id', 'action', 'accepted_at', 'withdrawn_at'])]
final class PolicyConsent extends Model
{
    protected function casts(): array
    {
        return ['accepted_at' => 'datetime', 'withdrawn_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(PolicyVersion::class, 'policy_version_id');
    }
}
