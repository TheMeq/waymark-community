<?php

namespace Tests\Feature\Gallery;

use App\Domain\Gallery\Actions\IngestCommunityPhoto;
use App\Domain\Gallery\Contracts\DecodedRasterImage;
use App\Domain\Gallery\Contracts\ImageMetadataReader;
use App\Domain\Gallery\Contracts\RasterImageTransformer;
use App\Domain\Gallery\Data\ImageMetadata;
use App\Domain\Gallery\Data\ImageVariantDefinition;
use App\Domain\Gallery\Data\TransformedRasterImage;
use App\Domain\Gallery\Services\GdRasterImageTransformer;
use App\Domain\Gallery\Services\PhpExifImageMetadataReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

final class ImageIngestionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config()->set('gallery.photos.disk', 'local');
        config()->set('gallery.photos.directory', 'community-photos');
        config()->set('gallery.processing.allowed_mime_types', ['image/png', 'image/jpeg']);
        config()->set('gallery.processing.max_upload_bytes', 4096);
        config()->set('gallery.processing.max_width', 2000);
        config()->set('gallery.processing.max_height', 2000);
        config()->set('gallery.processing.max_pixels', 1000000);
        config()->set('gallery.processing.max_memory_bytes', 10000000);
        config()->set('gallery.processing.source_retention', false);
        config()->set('gallery.processing.preferred_output_mime_types', ['image/jpeg']);
        config()->set('gallery.processing.variants', [
            'master' => ['max_width' => 1600, 'max_height' => 1600, 'quality' => 86],
            'large' => ['max_width' => 1200, 'max_height' => 1200, 'quality' => 84],
            'medium' => ['max_width' => 800, 'max_height' => 800, 'quality' => 82],
            'thumbnail' => ['max_width' => 400, 'max_height' => 400, 'quality' => 80],
        ]);
    }

    public function test_declared_and_decoded_raster_types_must_match_an_allow_list_before_processing(): void
    {
        $transformer = new RecordingRasterTransformer;

        try {
            $this->ingestor($transformer)->handle($this->upload('spoofed.jpg', 'image/jpeg', $this->pngFixture()));
            $this->fail('A declared JPEG containing PNG raster content was accepted.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('does not match', $exception->errors()['photo'][0]);
            $this->assertSame([], $transformer->calls);
        }
    }

    public function test_corrupt_raster_content_is_rejected_before_any_storage_write(): void
    {
        $transformer = new RecordingRasterTransformer;

        try {
            $this->ingestor($transformer)->handle($this->upload('corrupt.png', 'image/png', 'not an image'));
            $this->fail('Corrupt raster content was accepted.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('decoded', $exception->errors()['photo'][0]);
            $this->assertSame([], $transformer->calls);
            $this->assertSame([], Storage::disk('local')->allFiles('community-photos'));
        }
    }

    public function test_header_valid_but_truncated_raster_is_rejected_at_the_decoder_boundary_before_storage(): void
    {
        try {
            $this->ingestor(new GdRasterImageTransformer)->handle($this->upload('truncated.png', 'image/png', $this->truncatedPngFixture()));
            $this->fail('A header-valid but truncated PNG was accepted.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('decoded', $exception->errors()['photo'][0]);
            $this->assertSame([], Storage::disk('local')->allFiles('community-photos'));
        }
    }

    public function test_optional_input_codec_without_a_decoder_is_rejected_before_transform_or_storage(): void
    {
        $transformer = new RecordingRasterTransformer;
        config()->set('gallery.processing.allowed_mime_types', ['image/png', 'image/jpeg', 'image/webp']);

        try {
            $this->ingestor($transformer)->handle($this->upload('optional.webp', 'image/webp', $this->webpFixture()));
            $this->fail('An optional image type without a decoder was accepted.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('not supported by this server', $exception->errors()['photo'][0]);
            $this->assertSame([], $transformer->calls);
            $this->assertSame([], Storage::disk('local')->allFiles('community-photos'));
        }
    }

    public function test_size_and_pixel_budgets_are_enforced_before_the_transformer_is_called(): void
    {
        $transformer = new RecordingRasterTransformer;
        config()->set('gallery.processing.max_pixels', 1);

        try {
            $this->ingestor($transformer)->handle($this->upload('too-many-pixels.png', 'image/png', $this->pngFixture(2, 1)));
            $this->fail('A raster exceeding the pixel budget was accepted.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('pixel budget', $exception->errors()['photo'][0]);
            $this->assertSame([], $transformer->calls);
            $this->assertSame([], Storage::disk('local')->allFiles('community-photos'));
        }
    }

    public function test_file_size_limit_is_enforced_before_the_transformer_is_called(): void
    {
        $transformer = new RecordingRasterTransformer;
        config()->set('gallery.processing.max_upload_bytes', 1);

        try {
            $this->ingestor($transformer)->handle($this->upload('too-large.png', 'image/png', $this->pngFixture()));
            $this->fail('A raster exceeding the file-size limit was accepted.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('file-size limit', $exception->errors()['photo'][0]);
            $this->assertSame([], $transformer->calls);
        }
    }

    public function test_dimension_limit_is_enforced_before_the_transformer_is_called(): void
    {
        $transformer = new RecordingRasterTransformer;
        config()->set('gallery.processing.max_width', 30);

        try {
            $this->ingestor($transformer)->handle($this->upload('too-wide.png', 'image/png', $this->pngFixture(40, 20)));
            $this->fail('A raster exceeding the dimension limit was accepted.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('dimension limit', $exception->errors()['photo'][0]);
            $this->assertSame([], $transformer->calls);
        }
    }

    public function test_memory_budget_is_enforced_before_the_transformer_is_called(): void
    {
        $transformer = new RecordingRasterTransformer;
        config()->set('gallery.processing.max_memory_bytes', 1);

        try {
            $this->ingestor($transformer)->handle($this->upload('too-expensive.png', 'image/png', $this->pngFixture()));
            $this->fail('A raster exceeding the processing memory budget was accepted.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('memory budget', $exception->errors()['photo'][0]);
            $this->assertSame([], $transformer->calls);
        }
    }

    public function test_retained_source_is_included_in_the_preflight_memory_budget(): void
    {
        $transformer = new RecordingRasterTransformer;
        config()->set('gallery.processing.source_retention', true);
        config()->set('gallery.processing.retained_source', ['max_width' => 40, 'max_height' => 20, 'quality' => 88]);
        config()->set('gallery.processing.variants', [
            'master' => ['max_width' => 1, 'max_height' => 1, 'quality' => 86],
            'large' => ['max_width' => 1, 'max_height' => 1, 'quality' => 84],
            'medium' => ['max_width' => 1, 'max_height' => 1, 'quality' => 82],
            'thumbnail' => ['max_width' => 1, 'max_height' => 1, 'quality' => 80],
        ]);
        config()->set('gallery.processing.max_memory_bytes', 5000);

        try {
            $this->ingestor($transformer)->handle($this->upload('retained-source.png', 'image/png', $this->pngFixture(40, 20)));
            $this->fail('A retained source exceeding the processing memory budget was accepted.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('memory budget', $exception->errors()['photo'][0]);
            $this->assertSame([], $transformer->calls);
        }
    }

    public function test_rotated_raster_is_rejected_when_its_second_orientation_canvas_exceeds_the_memory_budget(): void
    {
        $transformer = new RecordingRasterTransformer;
        config()->set('gallery.processing.variants', [
            'master' => ['max_width' => 1, 'max_height' => 1, 'quality' => 86],
            'large' => ['max_width' => 1, 'max_height' => 1, 'quality' => 84],
            'medium' => ['max_width' => 1, 'max_height' => 1, 'quality' => 82],
            'thumbnail' => ['max_width' => 1, 'max_height' => 1, 'quality' => 80],
        ]);
        config()->set('gallery.processing.max_memory_bytes', 5000);

        try {
            $this->ingestor($transformer, new FixedImageMetadataReader(new ImageMetadata(6, null)))
                ->handle($this->upload('rotated.png', 'image/png', $this->pngFixture(40, 20)));
            $this->fail('A rotated raster exceeding the processing memory budget was accepted.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('memory budget', $exception->errors()['photo'][0]);
            $this->assertSame([], $transformer->calls);
        }
    }

    public function test_processing_configuration_is_complete_before_metadata_or_transform_work_begins(): void
    {
        $transformer = new RecordingRasterTransformer;
        $metadataReader = new RecordingMetadataReader;
        config()->set('gallery.processing.variants', [
            'large' => ['max_width' => 1200, 'max_height' => 1200, 'quality' => 84],
            'medium' => ['max_width' => 800, 'max_height' => 800, 'quality' => 82],
            'thumbnail' => ['max_width' => 400, 'max_height' => 400, 'quality' => 80],
        ]);

        try {
            $this->ingestor($transformer, $metadataReader)->handle($this->upload('walk.png', 'image/png', $this->pngFixture()));
            $this->fail('Processing started with a missing master variant definition.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('configuration', $exception->getMessage());
            $this->assertSame([], $metadataReader->paths);
            $this->assertSame([], $transformer->calls);
            $this->assertSame([], Storage::disk('local')->allFiles('community-photos'));
        }
    }

    public function test_processing_configuration_rejects_non_positive_resource_limits_before_metadata_or_transform_work(): void
    {
        $transformer = new RecordingRasterTransformer;
        $metadataReader = new RecordingMetadataReader;
        config()->set('gallery.processing.max_memory_bytes', 0);

        try {
            $this->ingestor($transformer, $metadataReader)->handle($this->upload('walk.png', 'image/png', $this->pngFixture()));
            $this->fail('Processing started with a non-positive memory limit.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('configuration', $exception->getMessage());
            $this->assertSame([], $metadataReader->paths);
            $this->assertSame([], $transformer->calls);
        }
    }

    public function test_processing_configuration_rejects_empty_output_preferences_before_metadata_or_transform_work(): void
    {
        $transformer = new RecordingRasterTransformer;
        $metadataReader = new RecordingMetadataReader;
        config()->set('gallery.processing.preferred_output_mime_types', []);

        try {
            $this->ingestor($transformer, $metadataReader)->handle($this->upload('walk.png', 'image/png', $this->pngFixture()));
            $this->fail('Processing started without a usable output codec preference.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('configuration', $exception->getMessage());
            $this->assertSame([], $metadataReader->paths);
            $this->assertSame([], $transformer->calls);
        }
    }

    public function test_ingestion_normalises_orientation_extracts_capture_time_and_stores_exif_free_bounded_variants(): void
    {
        $transformer = new RecordingRasterTransformer;
        $capturedAt = Carbon::parse('2026-08-20 09:15:00', 'Europe/London');

        $result = $this->ingestor($transformer, new FixedImageMetadataReader(new ImageMetadata(6, $capturedAt)))
            ->handle($this->upload('walk.png', 'image/png', $this->pngFixture()));

        $this->assertSame($capturedAt->toDateTimeString(), $result->capturedAt?->toDateTimeString());
        $this->assertSame(1, $result->width);
        $this->assertSame(1, $result->height);
        $this->assertNull($result->retainedSource);
        $this->assertSame(['master', 'large', 'medium', 'thumbnail'], array_keys($result->variants));
        $this->assertCount(1, $transformer->decodedPaths);
        $this->assertCount(4, $transformer->calls);
        $this->assertSame([6], $transformer->decodedOrientations);

        foreach ($result->variants as $variant) {
            $this->assertMatchesRegularExpression('#^community-photos/[0-9a-f-]+/(?:master|large|medium|thumbnail)\.jpg$#', $variant->path);
            Storage::disk('local')->assertExists($variant->path);
            $this->assertStringNotContainsString('Exif', Storage::disk('local')->get($variant->path));
        }
    }

    public function test_retained_source_is_a_reencoded_private_safe_reference_only_when_enabled(): void
    {
        $transformer = new RecordingRasterTransformer;
        config()->set('gallery.processing.source_retention', true);
        config()->set('gallery.processing.retained_source', ['max_width' => 1800, 'max_height' => 1800, 'quality' => 88]);

        $result = $this->ingestor($transformer)->handle($this->upload('walk.png', 'image/png', $this->pngFixture()));

        $this->assertNotNull($result->retainedSource);
        $this->assertMatchesRegularExpression('#^community-photos/[0-9a-f-]+/source\.jpg$#', $result->retainedSource->path);
        Storage::disk('local')->assertExists($result->retainedSource->path);
        $this->assertCount(5, $transformer->calls);
    }

    public function test_optional_codecs_fall_back_to_core_jpeg_when_not_supported(): void
    {
        $transformer = new RecordingRasterTransformer(['image/jpeg']);
        config()->set('gallery.processing.preferred_output_mime_types', ['image/avif', 'image/webp', 'image/jpeg']);

        $result = $this->ingestor($transformer)->handle($this->upload('walk.png', 'image/png', $this->pngFixture()));

        $this->assertSame('image/jpeg', $result->variants['master']->mimeType);
        $this->assertSame('jpg', pathinfo($result->variants['master']->path, PATHINFO_EXTENSION));
    }

    public function test_optional_only_output_preferences_fall_back_to_a_supported_core_codec(): void
    {
        $transformer = new RecordingRasterTransformer(['image/jpeg']);
        config()->set('gallery.processing.preferred_output_mime_types', ['image/avif']);

        $result = $this->ingestor($transformer)->handle($this->upload('walk.png', 'image/png', $this->pngFixture()));

        $this->assertSame('image/jpeg', $result->variants['master']->mimeType);
    }

    public function test_decoded_raster_is_released_when_storage_disk_resolution_fails(): void
    {
        $transformer = new RecordingRasterTransformer;
        Storage::shouldReceive('disk')->with('local')->andThrow(new RuntimeException('Storage disk is unavailable.'));

        try {
            $this->ingestor($transformer)->handle($this->upload('walk.png', 'image/png', $this->pngFixture()));
            $this->fail('A storage disk resolution failure was not propagated.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Storage disk is unavailable.', $exception->getMessage());
            $this->assertTrue($transformer->decodedRasters[0]->released);
        } finally {
            Storage::clearResolvedInstance('filesystem');
        }
    }

    public function test_a_transform_failure_removes_only_the_generated_photo_directory(): void
    {
        $transformer = new RecordingRasterTransformer(throwOnVariant: 'medium');
        Storage::disk('local')->put('unrelated/keep.txt', 'keep');

        try {
            $this->ingestor($transformer)->handle($this->upload('walk.png', 'image/png', $this->pngFixture()));
            $this->fail('An image transform failure was not propagated.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Raster transform failed.', $exception->getMessage());
            $this->assertSame([], Storage::disk('local')->allFiles('community-photos'));
            Storage::disk('local')->assertExists('unrelated/keep.txt');
        }
    }

    public function test_gd_processor_reencodes_a_rotated_raster_into_bounded_exif_free_derivatives(): void
    {
        config()->set('gallery.processing.variants', [
            'master' => ['max_width' => 30, 'max_height' => 30, 'quality' => 86],
            'large' => ['max_width' => 20, 'max_height' => 20, 'quality' => 84],
            'medium' => ['max_width' => 15, 'max_height' => 15, 'quality' => 82],
            'thumbnail' => ['max_width' => 10, 'max_height' => 10, 'quality' => 80],
        ]);

        $result = $this->ingestor(new GdRasterImageTransformer, new FixedImageMetadataReader(new ImageMetadata(6, null)))
            ->handle($this->upload('landscape.png', 'image/png', $this->pngFixture(40, 20)));

        $this->assertSame([20, 40], [$result->width, $result->height]);
        $this->assertSame([15, 30], $this->imageDimensions($result->variants['master']->path));
        $this->assertSame([10, 20], $this->imageDimensions($result->variants['large']->path));
        $this->assertSame([8, 15], $this->imageDimensions($result->variants['medium']->path));
        $this->assertSame([5, 10], $this->imageDimensions($result->variants['thumbnail']->path));

        foreach ($result->variants as $variant) {
            $this->assertStringNotContainsString('Exif', Storage::disk('local')->get($variant->path));
        }
    }

    public function test_genuine_exif_orientation_and_capture_time_are_extracted_normalised_and_stripped_from_every_stored_output(): void
    {
        config()->set('gallery.processing.variants', [
            'master' => ['max_width' => 20, 'max_height' => 40, 'quality' => 100],
            'large' => ['max_width' => 20, 'max_height' => 40, 'quality' => 100],
            'medium' => ['max_width' => 20, 'max_height' => 40, 'quality' => 100],
            'thumbnail' => ['max_width' => 20, 'max_height' => 40, 'quality' => 100],
        ]);
        config()->set('gallery.processing.source_retention', true);
        config()->set('gallery.processing.retained_source', ['max_width' => 20, 'max_height' => 40, 'quality' => 100]);

        $result = (new IngestCommunityPhoto(new GdRasterImageTransformer, new PhpExifImageMetadataReader))
            ->handle($this->upload('oriented-capture.jpg', 'image/jpeg', $this->orientedCaptureJpegFixture()));

        $this->assertSame('2026-08-20 09:15:00', $result->capturedAt?->toDateTimeString());
        $this->assertSame([20, 40], [$result->width, $result->height]);
        $this->assertSame([20, 40], $this->imageDimensions($result->variants['master']->path));

        $master = imagecreatefromstring(Storage::disk('local')->get($result->variants['master']->path));
        $top = imagecolorsforindex($master, imagecolorat($master, 10, 5));
        $bottom = imagecolorsforindex($master, imagecolorat($master, 10, 35));
        imagedestroy($master);
        $this->assertGreaterThan($top['blue'], $top['red']);
        $this->assertGreaterThan($bottom['red'], $bottom['blue']);

        foreach ([...$result->variants, $result->retainedSource] as $variant) {
            $this->assertNotNull($variant);
            $this->assertFalse($this->hasExif(Storage::disk('local')->get($variant->path)));
        }
    }

    private function ingestor(RasterImageTransformer $transformer, ?ImageMetadataReader $metadataReader = null): IngestCommunityPhoto
    {
        return new IngestCommunityPhoto($transformer, $metadataReader ?? new FixedImageMetadataReader(new ImageMetadata(1, null)));
    }

    private function upload(string $name, string $mimeType, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'waymark-image-test-');
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, $mimeType, UPLOAD_ERR_OK, true);
    }

    private function pngFixture(int $width = 1, int $height = 1): string
    {
        if ($width !== 1 || $height !== 1) {
            $image = imagecreatetruecolor($width, $height);
            imagefill($image, 0, 0, imagecolorallocate($image, 55, 89, 39));
            ob_start();
            imagepng($image);
            $contents = ob_get_clean();
            imagedestroy($image);

            return is_string($contents) ? $contents : '';
        }

        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL2fQAAAABJRU5ErkJggg==', true) ?: '';
    }

    private function truncatedPngFixture(): string
    {
        $header = pack('NNCCCCC', 40, 20, 8, 2, 0, 0, 0);
        $chunk = 'IHDR'.$header;

        return "\x89PNG\r\n\x1a\n".pack('N', strlen($header)).$chunk.pack('N', crc32($chunk));
    }

    private function webpFixture(): string
    {
        return base64_decode('UklGRjIAAABXRUJQVlA4ICYAAABQAQCdASoBAAEAAUAmJQBOgCgAAP70GLfFfyq9ZS99eqWuTcAAAA==', true) ?: '';
    }

    private function orientedCaptureJpegFixture(): string
    {
        $image = imagecreatetruecolor(40, 20);
        $red = imagecolorallocate($image, 220, 30, 30);
        $blue = imagecolorallocate($image, 25, 70, 210);
        imagefilledrectangle($image, 0, 0, 19, 19, $red);
        imagefilledrectangle($image, 20, 0, 39, 19, $blue);
        ob_start();
        imagejpeg($image, null, 100);
        $jpeg = ob_get_clean();
        imagedestroy($image);

        $tiff = 'II'.pack('v', 42).pack('V', 8)
            .pack('v', 2)
            .pack('vvVv', 0x0112, 3, 1, 6).pack('v', 0)
            .pack('vvVV', 0x8769, 4, 1, 38)
            .pack('V', 0)
            .pack('v', 1)
            .pack('vvVV', 0x9003, 2, 20, 56)
            .pack('V', 0)
            ."2026:08:20 09:15:00\0";
        $payload = "Exif\0\0".$tiff;

        return is_string($jpeg)
            ? substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($payload) + 2).$payload.substr($jpeg, 2)
            : '';
    }

    /** @return array{int, int} */
    private function imageDimensions(string $path): array
    {
        $image = getimagesizefromstring(Storage::disk('local')->get($path));

        return [(int) $image[0], (int) $image[1]];
    }

    private function hasExif(string $contents): bool
    {
        $path = tempnam(sys_get_temp_dir(), 'waymark-exif-test-');
        file_put_contents($path, $contents);
        $metadata = @exif_read_data($path, null, true, false);
        unlink($path);

        return is_array($metadata) && array_key_exists('IFD0', $metadata);
    }
}

final class RecordingRasterTransformer implements RasterImageTransformer
{
    /** @var list<array{variant: string, orientation: int, mimeType: string}> */
    public array $calls = [];

    /** @var list<string> */
    public array $decodedPaths = [];

    /** @var list<int> */
    public array $decodedOrientations = [];

    /** @var list<RecordingDecodedRaster> */
    public array $decodedRasters = [];

    /**
     * @param  list<string>  $supportedOutputMimeTypes
     * @param  list<string>  $supportedInputMimeTypes
     */
    public function __construct(
        private array $supportedOutputMimeTypes = ['image/jpeg'],
        private ?string $throwOnVariant = null,
        private array $supportedInputMimeTypes = ['image/jpeg', 'image/png'],
    ) {}

    public function supportsInput(string $mimeType): bool
    {
        return in_array($mimeType, $this->supportedInputMimeTypes, true);
    }

    public function supportsOutput(string $mimeType): bool
    {
        return in_array($mimeType, $this->supportedOutputMimeTypes, true);
    }

    public function decode(string $sourcePath, string $mimeType, int $orientation): DecodedRasterImage
    {
        $this->decodedPaths[] = $sourcePath;
        $this->decodedOrientations[] = $orientation;

        return tap(new RecordingDecodedRaster, fn (RecordingDecodedRaster $raster) => $this->decodedRasters[] = $raster);
    }

    public function transform(DecodedRasterImage $source, ImageVariantDefinition $variant, string $mimeType): TransformedRasterImage
    {
        $this->calls[] = ['variant' => $variant->name, 'orientation' => 1, 'mimeType' => $mimeType];

        if ($variant->name === $this->throwOnVariant) {
            throw new RuntimeException('Raster transform failed.');
        }

        return new TransformedRasterImage('safe-raster-without-metadata', 1, 1, $mimeType);
    }
}

final class RecordingDecodedRaster implements DecodedRasterImage
{
    public bool $released = false;

    public function width(): int
    {
        return 1;
    }

    public function height(): int
    {
        return 1;
    }

    public function release(): void
    {
        $this->released = true;
    }
}

final readonly class FixedImageMetadataReader implements ImageMetadataReader
{
    public function __construct(private ImageMetadata $metadata) {}

    public function read(string $path, string $mimeType): ImageMetadata
    {
        return $this->metadata;
    }
}

final class RecordingMetadataReader implements ImageMetadataReader
{
    /** @var list<string> */
    public array $paths = [];

    public function read(string $path, string $mimeType): ImageMetadata
    {
        $this->paths[] = $path;

        return new ImageMetadata(1, null);
    }
}
