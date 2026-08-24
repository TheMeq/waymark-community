<?php

namespace App\Domain\Operations\Installation\Contracts;

use App\Domain\Operations\Installation\EnvironmentWriteResult;

interface EnvironmentWriter
{
    /** @param array<string, string> $values */
    public function write(array $values): EnvironmentWriteResult;
}
