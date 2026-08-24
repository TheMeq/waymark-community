<?php

namespace App\Domain\Operations\Updates;

final readonly class UpdateCompatibilityReport
{
    /** @param list<array{key: string, label: string, status: string, message: string}> $checks */
    public function __construct(public array $checks) {}

    public function compatible(): bool
    {
        foreach ($this->checks as $check) {
            if ($check['status'] === 'blocker') {
                return false;
            }
        }

        return true;
    }
}
