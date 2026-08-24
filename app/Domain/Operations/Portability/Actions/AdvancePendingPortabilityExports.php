<?php

namespace App\Domain\Operations\Portability\Actions;

use App\Domain\Operations\Portability\Models\PortabilityExportRun;
use Throwable;

final readonly class AdvancePendingPortabilityExports
{
    public function __construct(private CreatePortabilityExport $exports) {}

    public function handle(int $runLimit = 1, int $componentLimit = 25): int
    {
        $runs = PortabilityExportRun::query()
            ->whereIn('status', ['queued', 'running'])
            ->oldest('id')
            ->limit(max(1, min($runLimit, 10)))
            ->get();

        foreach ($runs as $run) {
            try {
                $this->exports->advance($run, $componentLimit);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $runs->count();
    }
}
