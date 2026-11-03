<?php

namespace App\Domain\SiteMedia\Data;

final readonly class SiteMediaPresentation
{
    public function __construct(public int $id, public string $url, public string $alt, public int $width, public int $height, public float $focalPointX, public float $focalPointY) {}

    public function objectPosition(): string
    {
        return $this->percent($this->focalPointX).' '.$this->percent($this->focalPointY);
    }

    private function percent(float $value): string
    {
        $formatted = rtrim(rtrim(number_format(max(0, min(1, $value)) * 100, 2, '.', ''), '0'), '.');

        return $formatted.'%';
    }
}
