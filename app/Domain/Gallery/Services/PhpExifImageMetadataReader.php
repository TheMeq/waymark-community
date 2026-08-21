<?php

namespace App\Domain\Gallery\Services;

use App\Domain\Gallery\Contracts\ImageMetadataReader;
use App\Domain\Gallery\Data\ImageMetadata;
use Carbon\CarbonImmutable;
use RuntimeException;

final class PhpExifImageMetadataReader implements ImageMetadataReader
{
    public function read(string $path, string $mimeType): ImageMetadata
    {
        if ($mimeType !== 'image/jpeg') {
            return new ImageMetadata(1, null);
        }

        if (! function_exists('exif_read_data')) {
            throw new RuntimeException('Gallery image processing requires the EXIF PHP extension.');
        }

        $data = @exif_read_data($path, null, true, false);

        if (! is_array($data)) {
            return new ImageMetadata(1, null);
        }

        $orientation = $data['IFD0']['Orientation'] ?? 1;
        $capturedAt = $data['EXIF']['DateTimeOriginal']
            ?? $data['EXIF']['DateTimeDigitized']
            ?? $data['IFD0']['DateTime']
            ?? null;

        return new ImageMetadata(
            is_int($orientation) && $orientation >= 1 && $orientation <= 8 ? $orientation : 1,
            is_string($capturedAt) ? $this->captureTime($capturedAt) : null,
        );
    }

    private function captureTime(string $capturedAt): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat('Y:m:d H:i:s', $capturedAt, config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }
}
