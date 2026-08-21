<?php

namespace App\Domain\Gallery\Contracts;

interface DecodedRasterImage
{
    public function width(): int;

    public function height(): int;

    public function release(): void;
}
