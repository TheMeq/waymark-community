<?php

namespace App\Domain\Operations\Installation;

final readonly class InstallationAttempt
{
    public function __construct(
        public bool $successful,
        public EnvironmentWriteResult $environment,
        public string $message,
    ) {}
}
