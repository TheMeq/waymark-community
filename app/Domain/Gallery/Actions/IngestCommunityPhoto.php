<?php

namespace App\Domain\Gallery\Actions;

use App\Domain\Gallery\Contracts\ImageMetadataReader;
use App\Domain\Gallery\Contracts\RasterImageTransformer;
use App\Domain\Gallery\Data\ImageVariantDefinition;
use App\Domain\Gallery\Data\PhotoStorageReference;
use App\Domain\Gallery\Data\ProcessedCommunityPhoto;
use App\Domain\Gallery\Data\ProcessedPhotoVariant;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class IngestCommunityPhoto
{
    public function __construct(
        private RasterImageTransformer $transformer,
        private ImageMetadataReader $metadataReader,
    ) {}

    public function handle(UploadedFile $upload): ProcessedCommunityPhoto
    {
        [$path, $decodedMimeType, $width, $height] = $this->inspect($upload);
        $metadata = $this->metadataReader->read($path, $decodedMimeType);
        $orientation = $metadata->orientation >= 1 && $metadata->orientation <= 8 ? $metadata->orientation : 1;
        [$width, $height] = $this->normalisedDimensions($width, $height, $orientation);
        $this->ensureMemoryBudget($width, $height);
        $outputMimeType = $this->outputMimeType($decodedMimeType);
        $diskName = (string) config('gallery.photos.disk', 'local');
        $directory = trim((string) config('gallery.photos.directory', 'community-photos'), '/').'/'.Str::uuid()->toString();
        $disk = Storage::disk($diskName);

        try {
            $retainedSource = null;

            if ((bool) config('gallery.processing.source_retention', false)) {
                $retainedSource = $this->storeVariant(
                    $diskName,
                    $disk,
                    $directory,
                    $path,
                    $orientation,
                    $this->retainedSourceDefinition(),
                    $outputMimeType,
                );
            }

            $variants = [];

            foreach ((array) config('gallery.processing.variants', []) as $name => $definition) {
                $variants[(string) $name] = $this->storeVariant(
                    $diskName,
                    $disk,
                    $directory,
                    $path,
                    $orientation,
                    $this->variantDefinition((string) $name, $definition),
                    $outputMimeType,
                );
            }

            return new ProcessedCommunityPhoto($retainedSource, $variants, $width, $height, $metadata->capturedAt);
        } catch (\Throwable $exception) {
            $disk->deleteDirectory($directory);

            throw $exception;
        }
    }

    /** @return array{string, string, int, int} */
    private function inspect(UploadedFile $upload): array
    {
        if ($upload->getError() !== UPLOAD_ERR_OK) {
            throw ValidationException::withMessages(['photo' => 'The photo upload failed.']);
        }

        $path = $upload->getRealPath();

        if (! is_string($path) || ! is_file($path)) {
            throw ValidationException::withMessages(['photo' => 'The uploaded photo could not be read.']);
        }

        $declaredMimeType = strtolower((string) $upload->getClientMimeType());
        $allowedMimeTypes = (array) config('gallery.processing.allowed_mime_types', []);

        if (! in_array($declaredMimeType, $allowedMimeTypes, true)) {
            throw ValidationException::withMessages(['photo' => 'The uploaded photo has an unapproved MIME type.']);
        }

        $size = $upload->getSize() ?? filesize($path) ?: 0;

        if ($size > (int) config('gallery.processing.max_upload_bytes', 10 * 1024 * 1024)) {
            throw ValidationException::withMessages(['photo' => 'The uploaded photo exceeds the file-size limit.']);
        }

        $image = @getimagesize($path);

        if (! is_array($image) || ! isset($image[0], $image[1], $image['mime'])) {
            throw ValidationException::withMessages(['photo' => 'The uploaded photo could not be decoded as a raster image.']);
        }

        $decodedMimeType = strtolower((string) $image['mime']);

        if (! in_array($decodedMimeType, $allowedMimeTypes, true)) {
            throw ValidationException::withMessages(['photo' => 'The decoded photo format is not allowed.']);
        }

        if ($decodedMimeType !== $declaredMimeType) {
            throw ValidationException::withMessages(['photo' => 'The declared photo type does not match its decoded raster content.']);
        }

        $width = (int) $image[0];
        $height = (int) $image[1];

        if ($width > (int) config('gallery.processing.max_width', 6000)
            || $height > (int) config('gallery.processing.max_height', 6000)) {
            throw ValidationException::withMessages(['photo' => 'The uploaded photo exceeds the dimension limit.']);
        }

        if ($width * $height > (int) config('gallery.processing.max_pixels', 24000000)) {
            throw ValidationException::withMessages(['photo' => 'The uploaded photo exceeds the pixel budget.']);
        }

        return [$path, $decodedMimeType, $width, $height];
    }

    private function ensureMemoryBudget(int $width, int $height): void
    {
        $largestTargetPixels = 0;
        $definitions = (array) config('gallery.processing.variants', []);

        if ((bool) config('gallery.processing.source_retention', false)) {
            $definitions['source'] = (array) config('gallery.processing.retained_source', []);
        }

        foreach ($definitions as $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $largestTargetPixels = max(
                $largestTargetPixels,
                min($width, (int) ($definition['max_width'] ?? $width)) * min($height, (int) ($definition['max_height'] ?? $height)),
            );
        }

        $estimatedBytes = (($width * $height) + $largestTargetPixels) * 5;

        if ($estimatedBytes > (int) config('gallery.processing.max_memory_bytes', 128 * 1024 * 1024)) {
            throw ValidationException::withMessages(['photo' => 'The uploaded photo exceeds the processing memory budget.']);
        }
    }

    private function outputMimeType(string $decodedMimeType): string
    {
        foreach ((array) config('gallery.processing.preferred_output_mime_types', []) as $mimeType) {
            if (is_string($mimeType) && $this->transformer->supports($mimeType)) {
                return $mimeType;
            }
        }

        if ($this->transformer->supports($decodedMimeType)) {
            return $decodedMimeType;
        }

        throw new RuntimeException('Image processing is unavailable because this server has no supported raster codec.');
    }

    private function retainedSourceDefinition(): ImageVariantDefinition
    {
        return $this->variantDefinition('source', (array) config('gallery.processing.retained_source', []));
    }

    private function variantDefinition(string $name, mixed $definition): ImageVariantDefinition
    {
        if (! is_array($definition)
            || ! preg_match('/\A[a-z][a-z0-9_-]*\z/D', $name)
            || (int) ($definition['max_width'] ?? 0) < 1
            || (int) ($definition['max_height'] ?? 0) < 1
            || (int) ($definition['quality'] ?? -1) < 0
            || (int) ($definition['quality'] ?? 101) > 100) {
            throw new RuntimeException('Gallery image processing configuration is invalid.');
        }

        return new ImageVariantDefinition(
            $name,
            (int) $definition['max_width'],
            (int) $definition['max_height'],
            (int) $definition['quality'],
        );
    }

    private function storeVariant(
        string $diskName,
        FilesystemAdapter $disk,
        string $directory,
        string $sourcePath,
        int $orientation,
        ImageVariantDefinition $definition,
        string $mimeType,
    ): ProcessedPhotoVariant {
        $raster = $this->transformer->transform($sourcePath, $orientation, $definition, $mimeType);
        $path = $directory.'/'.$definition->name.'.'.$this->extensionFor($raster->mimeType);
        PhotoStorageReference::from($diskName, $path);

        if (! $disk->put($path, $raster->contents)) {
            throw new RuntimeException('The processed photo could not be stored.');
        }

        return new ProcessedPhotoVariant($path, $raster->mimeType, $raster->width, $raster->height, strlen($raster->contents));
    }

    /** @return array{int, int} */
    private function normalisedDimensions(int $width, int $height, int $orientation): array
    {
        return in_array($orientation, [5, 6, 7, 8], true) ? [$height, $width] : [$width, $height];
    }

    private function extensionFor(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            default => throw new RuntimeException('Image processing returned an unsupported raster codec.'),
        };
    }
}
