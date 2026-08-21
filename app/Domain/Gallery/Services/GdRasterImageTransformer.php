<?php

namespace App\Domain\Gallery\Services;

use App\Domain\Gallery\Contracts\DecodedRasterImage;
use App\Domain\Gallery\Contracts\RasterImageTransformer;
use App\Domain\Gallery\Data\ImageVariantDefinition;
use App\Domain\Gallery\Data\TransformedRasterImage;
use RuntimeException;

final class GdRasterImageTransformer implements RasterImageTransformer
{
    public function supportsInput(string $mimeType): bool
    {
        return match ($mimeType) {
            'image/jpeg' => function_exists('imagecreatefromjpeg'),
            'image/png' => function_exists('imagecreatefrompng'),
            'image/webp' => function_exists('imagecreatefromwebp'),
            'image/avif' => function_exists('imagecreatefromavif'),
            default => false,
        };
    }

    public function supportsOutput(string $mimeType): bool
    {
        return match ($mimeType) {
            'image/jpeg' => function_exists('imagejpeg'),
            'image/png' => function_exists('imagepng'),
            'image/webp' => function_exists('imagewebp'),
            'image/avif' => function_exists('imageavif'),
            default => false,
        };
    }

    public function decode(string $sourcePath, string $mimeType, int $orientation): DecodedRasterImage
    {
        if (! $this->supportsInput($mimeType)) {
            throw new RuntimeException('Image processing is unavailable because the server does not support the requested raster codec.');
        }

        $source = $this->createSourceImage($sourcePath);
        $normalised = $this->normaliseOrientation($source, $orientation);

        if ($normalised !== $source) {
            imagedestroy($source);
        }

        return new GdDecodedRasterImage($normalised);
    }

    public function transform(DecodedRasterImage $source, ImageVariantDefinition $variant, string $mimeType): TransformedRasterImage
    {
        if (! $source instanceof GdDecodedRasterImage || ! $this->supportsOutput($mimeType)) {
            throw new RuntimeException('Image processing is unavailable because the server does not support the requested raster codec.');
        }

        $sourceImage = $source->image();
        $width = $source->width();
        $height = $source->height();
        [$targetWidth, $targetHeight] = $this->boundedDimensions($width, $height, $variant);
        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($target === false) {
            throw new RuntimeException('Image processing could not allocate the target raster.');
        }

        try {
            $this->prepareTransparency($target, $mimeType);

            if (! imagecopyresampled($target, $sourceImage, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height)) {
                throw new RuntimeException('Image processing could not resize the raster.');
            }

            return new TransformedRasterImage(
                $this->encode($target, $mimeType, $variant->quality),
                $targetWidth,
                $targetHeight,
                $mimeType,
            );
        } finally {
            imagedestroy($target);
        }
    }

    private function createSourceImage(string $sourcePath): \GdImage
    {
        $details = @getimagesize($sourcePath);
        $type = is_array($details) ? ($details[2] ?? null) : null;
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            IMAGETYPE_AVIF => function_exists('imagecreatefromavif') ? @imagecreatefromavif($sourcePath) : false,
            default => false,
        };

        if (! $image instanceof \GdImage) {
            throw new RuntimeException('The uploaded image could not be decoded for processing.');
        }

        return $image;
    }

    private function normaliseOrientation(\GdImage $source, int $orientation): \GdImage
    {
        match ($orientation) {
            2 => imageflip($source, IMG_FLIP_HORIZONTAL),
            4 => imageflip($source, IMG_FLIP_VERTICAL),
            5, 7 => imageflip($source, IMG_FLIP_HORIZONTAL),
            default => null,
        };

        $angle = match ($orientation) {
            3 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $source;
        }

        $rotated = imagerotate($source, $angle, 0);

        if (! $rotated instanceof \GdImage) {
            throw new RuntimeException('Image processing could not normalise orientation.');
        }

        return $rotated;
    }

    /** @return array{int, int} */
    private function boundedDimensions(int $width, int $height, ImageVariantDefinition $variant): array
    {
        $ratio = min($variant->maxWidth / $width, $variant->maxHeight / $height, 1);

        return [max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio))];
    }

    private function prepareTransparency(\GdImage $target, string $mimeType): void
    {
        if (! in_array($mimeType, ['image/png', 'image/webp', 'image/avif'], true)) {
            return;
        }

        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefill($target, 0, 0, $transparent);
    }

    private function encode(\GdImage $image, string $mimeType, int $quality): string
    {
        ob_start();

        try {
            $encoded = match ($mimeType) {
                'image/jpeg' => imagejpeg($image, null, $quality),
                'image/png' => imagepng($image, null, max(0, min(9, (int) round((100 - $quality) / 11))), PNG_ALL_FILTERS),
                'image/webp' => imagewebp($image, null, $quality),
                'image/avif' => imageavif($image, null, $quality),
                default => false,
            };
            $contents = ob_get_clean();

            if (! $encoded || ! is_string($contents)) {
                throw new RuntimeException('Image processing could not encode the raster.');
            }

            return $contents;
        } catch (\Throwable $exception) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            throw $exception;
        }
    }
}
