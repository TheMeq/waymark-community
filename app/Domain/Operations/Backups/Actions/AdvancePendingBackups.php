<?php

namespace App\Domain\Operations\Backups\Actions;

use App\Domain\Operations\Backups\Models\BackupRun;
use Throwable;

final readonly class AdvancePendingBackups
{
    public function __construct(private CreateBackup $backups) {}

    public function handle(int $limit = 1): int
    {
        $runs = BackupRun::query()
            ->whereIn('status', ['queued', 'running'])
            ->oldest('id')
            ->limit(max(1, min($limit, 10)))
            ->get();
        $handled = 0;
        foreach ($runs as $run) {
            try {
                $this->backups->advance($run);
            } catch (Throwable $exception) {
                report($exception);
            }
            $handled++;
        }

        return $handled;
    }
}
