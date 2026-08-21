<?php

namespace App\Domain\Gallery\Data;

use App\Domain\Gallery\Contracts\RasterImageTransformer;
use RuntimeException;

final readonly class ImageProcessingConfiguration
{
    /**
     * @param  list<string>  $allowedMimeTypes
     * @param  array<string, ImageVariantDefinition>  $variants
     */
    private function __construct(
        public array $allowedMimeTypes,
        public int $maxUploadBytes,
        public int $maxWidth,
        public int $maxHeight,
        public int $maxPixels,
        public int $maxMemoryBytes,
        public ?ImageVariantDefinition $retainedSource,
        public array $variants,
        public string $outputMimeType,
    ) {}

    public static function from(array $configuration, RasterImageTransformer $transformer): self
    {
        $allowedMimeTypes = $configuration['allowed_mime_types'] ?? null;

        if (! is_array($allowedMimeTypes)
            || $allowedMimeTypes === []
            || array_diff($allowedMimeTypes, ['image/jpeg', 'image/png', 'image/webp', 'image/avif']) !== []) {
            throw new RuntimeException('Gallery image processing configuration is invalid.');
        }

        $limits = [];

        foreach (['max_upload_bytes', 'max_width', 'max_height', 'max_pixels', 'max_memory_bytes'] as $name) {
            $value = $configuration[$name] ?? null;

            if (! is_int($value) || $value < 1) {
                throw new RuntimeException('Gallery image processing configuration is invalid.');
            }

            $limits[$name] = $value;
        }

        $variants = $configuration['variants'] ?? null;

        if (! is_array($variants)) {
            throw new RuntimeException('Gallery image processing configuration is invalid.');
        }

        $compiledVariants = [];

        foreach (['master', 'large', 'medium', 'thumbnail'] as $name) {
            if (! array_key_exists($name, $variants)) {
                throw new RuntimeException('Gallery image processing configuration is invalid.');
            }

            $compiledVariants[$name] = self::variant($name, $variants[$name]);
        }

        $retainedSource = null;

        if (($configuration['source_retention'] ?? false) === true) {
            $retainedSource = self::variant('source', $configuration['retained_source'] ?? null);
        }

        $preferences = $configuration['preferred_output_mime_types'] ?? null;

        if (! is_array($preferences) || $preferences === []) {
            throw new RuntimeException('Gallery image processing configuration is invalid.');
        }

        $outputMimeType = null;

        foreach ($preferences as $mimeType) {
            if (! is_string($mimeType) || ! in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp', 'image/avif'], true)) {
                throw new RuntimeException('Gallery image processing configuration is invalid.');
            }

            if ($outputMimeType === null && $transformer->supportsOutput($mimeType)) {
                $outputMimeType = $mimeType;
            }
        }

        if ($outputMimeType === null) {
            foreach (['image/jpeg', 'image/png'] as $coreMimeType) {
                if ($transformer->supportsOutput($coreMimeType)) {
                    $outputMimeType = $coreMimeType;

                    break;
                }
            }
        }

        if ($outputMimeType === null) {
            throw new RuntimeException('Gallery image processing configuration is invalid.');
        }

        return new self(
            array_values($allowedMimeTypes),
            $limits['max_upload_bytes'],
            $limits['max_width'],
            $limits['max_height'],
            $limits['max_pixels'],
            $limits['max_memory_bytes'],
            $retainedSource,
            $compiledVariants,
            $outputMimeType,
        );
    }

    private static function variant(string $name, mixed $definition): ImageVariantDefinition
    {
        if (! is_array($definition)
            || ! preg_match('/\A[a-z][a-z0-9_-]*\z/D', $name)
            || ! is_int($definition['max_width'] ?? null)
            || ! is_int($definition['max_height'] ?? null)
            || ! is_int($definition['quality'] ?? null)
            || $definition['max_width'] < 1
            || $definition['max_height'] < 1
            || $definition['quality'] < 0
            || $definition['quality'] > 100) {
            throw new RuntimeException('Gallery image processing configuration is invalid.');
        }

        return new ImageVariantDefinition($name, $definition['max_width'], $definition['max_height'], $definition['quality']);
    }
}
