<?php

namespace App\Domain\Operations\Installation;

final readonly class DatabaseConnectionResult
{
    public function __construct(
        public bool $successful,
        public string $message,
    ) {}
}
