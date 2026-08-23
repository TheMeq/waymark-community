<?php

namespace App\Domain\Governance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['document_id', 'version_number', 'storage_disk', 'storage_path', 'original_filename', 'mime_type', 'file_size_bytes', 'created_by_user_id', 'published_at'])]
final class DocumentVersion extends Model
{
    protected static function booted(): void
    {
        self::saving(function (self $version): void {
            if ($version->storage_disk !== config('filesystems.default', 'local') || preg_match('#\Adocuments/[a-f0-9-]{36}/v[1-9][0-9]*\.(?:pdf|docx?|xlsx?|odt)\z#Di', $version->storage_path) !== 1) {
                throw ValidationException::withMessages(['storage_path' => 'Document versions must use generated private document paths.']);
            }
        });
    }

    protected function casts(): array
    {
        return ['version_number' => 'integer', 'file_size_bytes' => 'integer', 'published_at' => 'datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
