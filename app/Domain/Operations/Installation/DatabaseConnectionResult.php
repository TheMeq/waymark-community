<?php

namespace App\Domain\Operations\Installation;

final readonly class DatabaseConnectionResult
{
    public function __construct(
        public bool $successful,
        public string $message,
        public DatabaseInstallationState $state = DatabaseInstallationState::Ambiguous,
        public bool $resetSafe = false,
        public string $fingerprint = '',
    ) {}
}
