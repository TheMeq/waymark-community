<?php

namespace App\Domain\Gallery\Contracts;

use App\Domain\Gallery\Data\ImageVariantDefinition;
use App\Domain\Gallery\Data\TransformedRasterImage;

interface RasterImageTransformer
{
    public function supports(string $mimeType): bool;

    public function transform(string $sourcePath, int $orientation, ImageVariantDefinition $variant, string $mimeType): TransformedRasterImage;
}
