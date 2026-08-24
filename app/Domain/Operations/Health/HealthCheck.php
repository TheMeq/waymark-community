<?php

namespace App\Domain\Operations\Health;

final readonly class HealthCheck
{
    public function __construct(
        public string $key,
        public string $label,
        public string $status,
        public string $message,
    ) {}
}
