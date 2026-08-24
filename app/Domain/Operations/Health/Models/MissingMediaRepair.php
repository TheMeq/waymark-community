<?php

namespace App\Domain\Operations\Health\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['reference_hash', 'media_type', 'record_id', 'storage_disk', 'path', 'status', 'detected_at', 'last_checked_at', 'resolved_at'])]
final class MissingMediaRepair extends Model
{
    protected function casts(): array
    {
        return [
            'record_id' => 'integer',
            'detected_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public static function referenceHash(string $mediaType, ?int $recordId, string $disk, string $path): string
    {
        return hash('sha256', implode("\0", [$mediaType, (string) $recordId, $disk, $path]));
    }
}
