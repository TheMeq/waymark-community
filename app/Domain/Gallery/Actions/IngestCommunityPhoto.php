<?php

namespace App\Domain\Gallery\Actions;

use App\Domain\Gallery\Contracts\DecodedRasterImage;
use App\Domain\Gallery\Contracts\ImageMetadataReader;
use App\Domain\Gallery\Contracts\RasterImageTransformer;
use App\Domain\Gallery\Data\ImageProcessingConfiguration;
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
        $configuration = ImageProcessingConfiguration::from((array) config('gallery.processing', []), $this->transformer);
        [$path, $decodedMimeType, $width, $height] = $this->inspect($upload, $configuration);

        if (! $this->transformer->supportsInput($decodedMimeType)) {
            throw ValidationException::withMessages(['photo' => 'The uploaded photo format is not supported by this server.']);
        }

        $metadata = $this->metadataReader->read($path, $decodedMimeType);
        $orientation = $metadata->orientation >= 1 && $metadata->orientation <= 8 ? $metadata->orientation : 1;
        [$width, $height] = $this->normalisedDimensions($width, $height, $orientation);
        $this->ensureMemoryBudget($width, $height, $orientation, $configuration);

        try {
            $source = $this->transformer->decode($path, $decodedMimeType, $orientation);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['photo' => 'The uploaded photo could not be decoded for processing.']);
        }

        try {
            $diskName = (string) config('gallery.photos.disk', 'local');
            $directory = trim((string) config('gallery.photos.directory', 'community-photos'), '/').'/'.Str::uuid()->toString();
            $disk = Storage::disk($diskName);

            try {
                $retainedSource = null;

                if ($configuration->retainedSource !== null) {
                    $retainedSource = $this->storeVariant(
                        $diskName,
                        $disk,
                        $directory,
                        $source,
                        $configuration->retainedSource,
                        $configuration->outputMimeType,
                    );
                }

                $variants = [];

                foreach ($configuration->variants as $name => $definition) {
                    $variants[$name] = $this->storeVariant(
                        $diskName,
                        $disk,
                        $directory,
                        $source,
                        $definition,
                        $configuration->outputMimeType,
                    );
                }

                return new ProcessedCommunityPhoto($retainedSource, $variants, $width, $height, $metadata->capturedAt);
            } catch (\Throwable $exception) {
                $disk->deleteDirectory($directory);

                throw $exception;
            }
        } finally {
            $source->release();
        }
    }

    public function validateForDeferredProcessing(UploadedFile $upload): void
    {
        $configuration = ImageProcessingConfiguration::from((array) config('gallery.processing', []), $this->transformer);
        [, $decodedMimeType] = $this->inspect($upload, $configuration);

        if (! $this->transformer->supportsInput($decodedMimeType)) {
            throw ValidationException::withMessages(['photo' => 'The uploaded photo format is not supported by this server.']);
        }
    }

    /** @return array{string, string, int, int} */
    private function inspect(UploadedFile $upload, ImageProcessingConfiguration $configuration): array
    {
        if ($upload->getError() !== UPLOAD_ERR_OK) {
            throw ValidationException::withMessages(['photo' => 'The photo upload failed.']);
        }

        $path = $upload->getRealPath();

        if (! is_string($path) || ! is_file($path)) {
            throw ValidationException::withMessages(['photo' => 'The uploaded photo could not be read.']);
        }

        $declaredMimeType = strtolower((string) $upload->getClientMimeType());
        $allowedMimeTypes = $configuration->allowedMimeTypes;

        if (! in_array($declaredMimeType, $allowedMimeTypes, true)) {
            throw ValidationException::withMessages(['photo' => 'The uploaded photo has an unapproved MIME type.']);
        }

        $size = $upload->getSize() ?? filesize($path) ?: 0;

        if ($size > $configuration->maxUploadBytes) {
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

        if ($width > $configuration->maxWidth || $height > $configuration->maxHeight) {
            throw ValidationException::withMessages(['photo' => 'The uploaded photo exceeds the dimension limit.']);
        }

        if ($width * $height > $configuration->maxPixels) {
            throw ValidationException::withMessages(['photo' => 'The uploaded photo exceeds the pixel budget.']);
        }

        return [$path, $decodedMimeType, $width, $height];
    }

    private function ensureMemoryBudget(int $width, int $height, int $orientation, ImageProcessingConfiguration $configuration): void
    {
        $largestTargetPixels = 0;
        $definitions = $configuration->variants;

        if ($configuration->retainedSource !== null) {
            $definitions['source'] = $configuration->retainedSource;
        }

        foreach ($definitions as $definition) {

            $largestTargetPixels = max(
                $largestTargetPixels,
                min($width, $definition->maxWidth) * min($height, $definition->maxHeight),
            );
        }

        $sourcePixels = $width * $height;
        $orientationPixels = in_array($orientation, [5, 6, 7, 8], true) ? $sourcePixels : 0;
        $estimatedBytes = ($sourcePixels + $orientationPixels + $largestTargetPixels) * 5;

        if ($estimatedBytes > $configuration->maxMemoryBytes) {
            throw ValidationException::withMessages(['photo' => 'The uploaded photo exceeds the processing memory budget.']);
        }
    }

    private function storeVariant(
        string $diskName,
        FilesystemAdapter $disk,
        string $directory,
        DecodedRasterImage $source,
        ImageVariantDefinition $definition,
        string $mimeType,
    ): ProcessedPhotoVariant {
        $raster = $this->transformer->transform($source, $definition, $mimeType);
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
