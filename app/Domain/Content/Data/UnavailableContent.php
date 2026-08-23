<?php

namespace App\Domain\Content\Data;

final readonly class UnavailableContent
{
    public function __construct(public string $title) {}
}
