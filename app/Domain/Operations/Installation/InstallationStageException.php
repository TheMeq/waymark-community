<?php

namespace App\Domain\Operations\Installation;

use RuntimeException;

final class InstallationStageException extends RuntimeException
{
    public function __construct(
        public readonly string $category,
        public readonly string $safeMessage,
        public readonly bool $changed,
    ) {
        parent::__construct($safeMessage);
    }
}
