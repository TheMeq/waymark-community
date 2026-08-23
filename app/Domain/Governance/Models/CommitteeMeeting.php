<?php

namespace App\Domain\Governance\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['title', 'meeting_date', 'agenda', 'attendee_metadata', 'internal_notes', 'minutes_document_version_id', 'visibility'])]
final class CommitteeMeeting extends Model
{
    protected static function booted(): void
    {
        self::saving(function (self $meeting): void {
            if (! in_array($meeting->visibility, ['public', 'committee'], true)) {
                throw ValidationException::withMessages(['visibility' => 'Choose public or committee visibility.']);
            }
        });
    }

    protected function casts(): array
    {
        return ['meeting_date' => 'date', 'attendee_metadata' => 'array'];
    }

    public function minutesVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'minutes_document_version_id');
    }
}
