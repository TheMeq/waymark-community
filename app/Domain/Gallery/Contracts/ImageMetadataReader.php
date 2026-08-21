<?php

namespace App\Domain\Gallery\Contracts;

use App\Domain\Gallery\Data\ImageMetadata;

interface ImageMetadataReader
{
    public function read(string $path, string $mimeType): ImageMetadata;
}
