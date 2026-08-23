<?php

namespace App\Domain\Governance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

#[Fillable(['document_category_id', 'title', 'slug', 'description', 'visibility', 'publication_date', 'public_version_history', 'current_version_id', 'download_count', 'controlled', 'approval_status', 'approver_id', 'approved_at', 'review_date', 'review_email_reminder', 'last_review_reminder_sent_at'])]
final class Document extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        self::saving(function (self $document): void {
            if (! in_array($document->visibility, ['public', 'registered', 'leader', 'committee'], true) || ! in_array($document->approval_status, ['draft', 'approved'], true)) {
                throw ValidationException::withMessages(['visibility' => 'Choose an approved document visibility and state.']);
            }
        });
    }

    protected function casts(): array
    {
        return ['publication_date' => 'date', 'public_version_history' => 'boolean', 'download_count' => 'integer', 'controlled' => 'boolean', 'approved_at' => 'datetime', 'review_date' => 'date', 'review_email_reminder' => 'boolean', 'last_review_reminder_sent_at' => 'datetime'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'current_version_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
