<?php

namespace App\Domain\Operations\Scheduling;

use App\Domain\Accounts\Actions\ProcessPersonalDataExports;
use App\Domain\Gallery\Actions\ProcessDeferredCommunityPhotos;
use App\Domain\Operations\Scheduling\Contracts\FallbackWorkload;
use Throwable;

final readonly class WaymarkFallbackWorkload implements FallbackWorkload
{
    public function __construct(
        private ProcessDeferredCommunityPhotos $photos,
        private ProcessPersonalDataExports $exports,
    ) {}

    public function run(): array
    {
        return [
            'deferred_photos' => $this->runSafely(fn (): int => $this->photos->handle(1)),
            'personal_data_exports' => $this->runSafely(fn (): int => $this->exports->handle(1)),
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
