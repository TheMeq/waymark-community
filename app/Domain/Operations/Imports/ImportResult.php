<?php

namespace App\Domain\Operations\Imports;

final readonly class ImportResult
{
    public function __construct(
        public int $imported,
        public int $duplicatesSkipped,
        public int $invalid,
        public bool $committed,
        public ImportPreview $preview,
    ) {}
}
