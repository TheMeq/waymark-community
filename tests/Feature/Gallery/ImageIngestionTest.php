<?php

namespace Tests\Feature\Gallery;

use App\Domain\Gallery\Actions\IngestCommunityPhoto;
use App\Domain\Gallery\Contracts\ImageMetadataReader;
use App\Domain\Gallery\Contracts\RasterImageTransformer;
use App\Domain\Gallery\Data\ImageMetadata;
use App\Domain\Gallery\Data\ImageVariantDefinition;
use App\Domain\Gallery\Data\TransformedRasterImage;
use App\Domain\Gallery\Services\GdRasterImageTransformer;
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

    public function test_size_and_pixel_budgets_are_enforced_before_the_transformer_is_called(): void
    {
        $transformer = new RecordingRasterTransformer;
        config()->set('gallery.processing.max_pixels', 0);

        try {
            $this->ingestor($transformer)->handle($this->upload('too-many-pixels.png', 'image/png', $this->pngFixture()));
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
        $this->assertCount(4, $transformer->calls);
        $this->assertSame(6, $transformer->calls[0]['orientation']);

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

    /** @return array{int, int} */
    private function imageDimensions(string $path): array
    {
        $image = getimagesizefromstring(Storage::disk('local')->get($path));

        return [(int) $image[0], (int) $image[1]];
    }
}

final class RecordingRasterTransformer implements RasterImageTransformer
{
    /** @var list<array{variant: string, orientation: int, mimeType: string}> */
    public array $calls = [];

    /** @param list<string> $supportedMimeTypes */
    public function __construct(private array $supportedMimeTypes = ['image/jpeg'], private ?string $throwOnVariant = null) {}

    public function supports(string $mimeType): bool
    {
        return in_array($mimeType, $this->supportedMimeTypes, true);
    }

    public function transform(string $sourcePath, int $orientation, ImageVariantDefinition $variant, string $mimeType): TransformedRasterImage
    {
        $this->calls[] = ['variant' => $variant->name, 'orientation' => $orientation, 'mimeType' => $mimeType];

        if ($variant->name === $this->throwOnVariant) {
            throw new RuntimeException('Raster transform failed.');
        }

        return new TransformedRasterImage('safe-raster-without-metadata', 1, 1, $mimeType);
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
