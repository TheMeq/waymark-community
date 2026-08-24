<?php

namespace App\Domain\Operations\Health;

final readonly class ScanMissingMediaResult
{
    public function __construct(
        public int $checked,
        public int $missing,
    ) {}
}
