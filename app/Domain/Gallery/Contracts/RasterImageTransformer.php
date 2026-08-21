<?php

namespace App\Domain\Gallery\Contracts;

use App\Domain\Gallery\Data\ImageVariantDefinition;
use App\Domain\Gallery\Data\TransformedRasterImage;

interface RasterImageTransformer
{
    public function supportsInput(string $mimeType): bool;

    public function supportsOutput(string $mimeType): bool;

    public function decode(string $sourcePath, string $mimeType, int $orientation): DecodedRasterImage;

    public function transform(DecodedRasterImage $source, ImageVariantDefinition $variant, string $mimeType): TransformedRasterImage;
}
