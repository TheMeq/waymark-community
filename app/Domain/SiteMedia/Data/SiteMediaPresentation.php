<?php

namespace App\Domain\SiteMedia\Data;

final readonly class SiteMediaPresentation
{
    public function __construct(public int $id, public string $url, public string $alt, public int $width, public int $height, public float $focalPointX, public float $focalPointY) {}
}
