<?php

namespace App\Domain\Operations\Scheduling\Contracts;

interface FallbackWorkload
{
    /** @return array<string, int> */
    public function run(): array;
}
