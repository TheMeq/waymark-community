<?php

namespace App\Domain\Operations\Portability\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['status', 'stage', 'work_state', 'retryable', 'storage_disk', 'storage_path', 'sha256', 'size_bytes', 'failure_message', 'last_progress_at', 'started_at', 'completed_at'])]
final class PortabilityExportRun extends Model
{
    protected function casts(): array
    {
        return [
            'work_state' => 'array',
            'retryable' => 'boolean',
            'size_bytes' => 'integer',
            'last_progress_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
