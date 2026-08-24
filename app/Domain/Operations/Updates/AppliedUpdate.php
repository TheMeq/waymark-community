<?php

namespace App\Domain\Operations\Updates;

final readonly class AppliedUpdate
{
    public function __construct(public string $version) {}
}
