<?php

namespace App\Domain\Communication\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['policy_page_id', 'version_number', 'body', 'publication_state', 'published_at'])]
final class PolicyVersion extends Model
{
    protected function casts(): array { return ['version_number' => 'integer', 'published_at' => 'datetime']; }
    public function page(): BelongsTo { return $this->belongsTo(PolicyPage::class, 'policy_page_id'); }
}
