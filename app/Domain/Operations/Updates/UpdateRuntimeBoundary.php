<?php

namespace App\Domain\Operations\Updates;

final readonly class UpdateRuntimeBoundary
{
    public string $id;

    public function __construct()
    {
        $this->id = bin2hex(random_bytes(32));
    }
}
