<?php

namespace App\Domain\Operations\Data;

final readonly class CreatedBrandingPreview
{
    public function __construct(public string $token) {}
}
