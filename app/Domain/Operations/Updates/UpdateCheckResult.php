<?php

namespace App\Domain\Operations\Updates;

final readonly class UpdateCheckResult
{
    public function __construct(
        public ReleaseMetadata $metadata,
        public UpdateCompatibilityReport $compatibility,
        public bool $updateAvailable,
    ) {}
}
