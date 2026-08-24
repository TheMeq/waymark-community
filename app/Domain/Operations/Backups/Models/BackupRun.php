<?php

namespace App\Domain\Operations\Backups\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['status', 'trigger', 'stage', 'work_state', 'encrypted_passphrase', 'retryable', 'last_progress_at', 'storage_disk', 'storage_path', 'sha256', 'size_bytes', 'encrypted', 'failure_message', 'started_at', 'completed_at'])]
final class BackupRun extends Model
{
    protected $hidden = ['encrypted_passphrase'];

    protected function casts(): array
    {
        return [
            'work_state' => 'array',
            'retryable' => 'boolean',
            'last_progress_at' => 'datetime',
            'size_bytes' => 'integer',
            'encrypted' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
