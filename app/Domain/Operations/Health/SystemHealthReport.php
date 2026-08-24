<?php

namespace App\Domain\Operations\Health;

final readonly class SystemHealthReport
{
    /** @param list<HealthCheck> $checks */
    public function __construct(public array $checks) {}

    public function serious(): bool
    {
        return collect($this->checks)->contains(fn (HealthCheck $check): bool => $check->status === 'critical');
    }
}
