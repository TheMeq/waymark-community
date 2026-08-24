<?php

namespace App\Domain\Operations\Backups\Actions;

use App\Domain\Operations\Backups\Models\BackupRun;
use Illuminate\Support\Facades\Storage;

final class PruneBackups
{
    public function handle(?int $keep = null): int
    {
        $keep = max(1, min($keep ?? (int) config('waymark.backups.retention_count', 14), 100));
        $removed = 0;

        BackupRun::query()->where('status', 'completed')->latest('completed_at')->latest('id')->skip($keep)->take(1000)->get()
            ->each(function (BackupRun $backup) use (&$removed): void {
                if (is_string($backup->storage_disk) && is_string($backup->storage_path)) {
                    Storage::disk($backup->storage_disk)->delete($backup->storage_path);
                }
                $backup->update(['status' => 'pruned', 'storage_path' => null]);
                $removed++;
            });

        return $removed;
    }
}
