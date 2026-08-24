<?php

namespace App\Domain\Operations\Installation;

use InvalidArgumentException;

final readonly class PreflightReport
{
    /** @param list<PreflightCheck> $checks */
    public function __construct(public array $checks) {}

    public function blocked(): bool
    {
        foreach ($this->checks as $check) {
            if ($check->status === 'blocker') {
                return true;
            }
        }

        return false;
    }

    public function check(string $key): PreflightCheck
    {
        foreach ($this->checks as $check) {
            if ($check->key === $key) {
                return $check;
            }
        }

        throw new InvalidArgumentException("Unknown server check [{$key}].");
    }
}
