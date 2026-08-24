<?php

namespace App\Domain\Operations\Updates\Contracts;

interface UpdateRuntime
{
    public function activate(string $version, string $applicationRoot): void;
}
