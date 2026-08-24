<?php

namespace App\Domain\Operations\Updates;

final readonly class UpdateEnvironment
{
    /** @param list<string> $extensions */
    public function __construct(
        public string $phpVersion,
        public array $extensions,
        public string $databaseFamily,
        public string $databaseVersion,
        public int $diskFreeBytes,
    ) {}
}
