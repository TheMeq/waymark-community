<?php

namespace App\Domain\Operations\Scheduling;

use Illuminate\Support\Carbon;

final readonly class SchedulerStatus
{
    public function __construct(
        public string $state,
        public ?Carbon $lastCronRunAt,
        public string $message,
    ) {}

    public function healthy(): bool
    {
        return $this->state === 'healthy';
    }
}
