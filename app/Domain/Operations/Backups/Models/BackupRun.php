<?php

namespace App\Domain\Operations\Backups\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['status', 'trigger', 'storage_disk', 'storage_path', 'sha256', 'size_bytes', 'encrypted', 'failure_message', 'started_at', 'completed_at'])]
final class BackupRun extends Model
{
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'encrypted' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
