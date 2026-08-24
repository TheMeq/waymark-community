<?php

namespace App\Domain\Operations\Installation;

final readonly class EnvironmentWriteResult
{
    public function __construct(
        public bool $written,
        public string $contents,
        public string $instructions,
    ) {}
}
