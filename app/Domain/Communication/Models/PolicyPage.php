<?php

namespace App\Domain\Communication\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['policy_key', 'title', 'slug', 'review_notice', 'current_version_id'])]
final class PolicyPage extends Model
{
    public function versions(): HasMany { return $this->hasMany(PolicyVersion::class); }
    public function currentVersion(): BelongsTo { return $this->belongsTo(PolicyVersion::class, 'current_version_id'); }
}
