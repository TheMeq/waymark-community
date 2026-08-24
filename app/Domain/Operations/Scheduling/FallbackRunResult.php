<?php

namespace App\Domain\Operations\Scheduling;

final readonly class FallbackRunResult
{
    /** @param array<string, int> $handled */
    public function __construct(
        public bool $ran,
        public string $reason,
        public array $handled = [],
    ) {}
}
