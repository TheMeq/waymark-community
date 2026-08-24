<?php

namespace App\Domain\Operations\Installation;

final readonly class PreflightCheck
{
    public function __construct(
        public string $key,
        public string $label,
        public string $status,
        public string $message,
        public ?string $remediation = null,
    ) {}
}
