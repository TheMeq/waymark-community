<?php

namespace App\Domain\Operations\Scheduling;

use App\Domain\Accounts\Actions\ProcessPersonalDataExports;
use App\Domain\Gallery\Actions\ProcessDeferredCommunityPhotos;
use App\Domain\Operations\Backups\Actions\AdvancePendingBackups;
use App\Domain\Operations\Maintenance\MaintenanceManager;
use App\Domain\Operations\Scheduling\Contracts\FallbackWorkload;
use Throwable;

final readonly class WaymarkFallbackWorkload implements FallbackWorkload
{
    public function __construct(
        private ProcessDeferredCommunityPhotos $photos,
        private ProcessPersonalDataExports $exports,
        private AdvancePendingBackups $backups,
        private MaintenanceManager $maintenance,
    ) {}

    public function run(): array
    {
        if ($this->maintenance->active()) {
            return [
                'deferred_photos' => 0,
                'personal_data_exports' => 0,
                'backup_steps' => $this->runSafely(fn (): int => $this->backups->handle(1)),
            ];
        }

        return [
            'deferred_photos' => $this->runSafely(fn (): int => $this->photos->handle(1)),
            'personal_data_exports' => $this->runSafely(fn (): int => $this->exports->handle(1)),
            'backup_steps' => $this->runSafely(fn (): int => $this->backups->handle(1)),
        ];
    }

    private function runSafely(callable $work): int
    {
        try {
            return $work();
        } catch (Throwable $exception) {
            report($exception);

            return 0;
        }
    }
}
