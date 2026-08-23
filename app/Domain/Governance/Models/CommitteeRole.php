<?php

namespace App\Domain\Governance\Models;

use App\Domain\SiteMedia\Models\SiteMedia;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title', 'person_id', 'public_name', 'sort_order', 'start_date', 'end_date', 'active', 'publicly_visible', 'public_photo_media_id', 'public_details', 'private_email', 'private_phone', 'private_notes'])]
final class CommitteeRole extends Model
{
    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'start_date' => 'date', 'end_date' => 'date', 'active' => 'boolean', 'publicly_visible' => 'boolean'];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(User::class, 'person_id');
    }

    public function publicPhotoMedia(): BelongsTo
    {
        return $this->belongsTo(SiteMedia::class, 'public_photo_media_id');
    }

    public function displayName(): string
    {
        return filled($this->public_name) ? $this->public_name : ($this->person?->publicDisplayName() ?? 'Vacant');
    }
}
