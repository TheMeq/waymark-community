<?php

namespace App\Domain\Operations\Installation;

final readonly class SetupSubmissionResult
{
    /**
     * @param  array<string, string|list<string>>  $errors
     * @param  array<string, mixed>  $oldInput
     */
    public function __construct(
        public bool $successful,
        public array $errors = [],
        public array $oldInput = [],
    ) {}
}
