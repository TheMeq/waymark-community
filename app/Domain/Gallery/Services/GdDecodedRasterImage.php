<?php

namespace App\Domain\Gallery\Services;

use App\Domain\Gallery\Contracts\DecodedRasterImage;

final class GdDecodedRasterImage implements DecodedRasterImage
{
    public function __construct(private ?\GdImage $image) {}

    public function width(): int
    {
        return imagesx($this->image());
    }

    public function height(): int
    {
        return imagesy($this->image());
    }

    public function image(): \GdImage
    {
        if (! $this->image instanceof \GdImage) {
            throw new \LogicException('The decoded raster has already been released.');
        }

        return $this->image;
    }

    public function release(): void
    {
        if ($this->image instanceof \GdImage) {
            imagedestroy($this->image);
            $this->image = null;
        }
    }
}
