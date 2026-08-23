<?php

namespace App\Domain\Governance\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'sort_order', 'review_reminders_enabled'])]
final class DocumentCategory extends Model
{
    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'review_reminders_enabled' => 'boolean'];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
