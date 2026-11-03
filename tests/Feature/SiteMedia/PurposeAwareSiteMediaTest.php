<?php

namespace Tests\Feature\SiteMedia;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Gallery\Actions\IngestCommunityPhoto;
use App\Domain\Gallery\Contracts\DecodedRasterImage;
use App\Domain\Gallery\Contracts\RasterImageTransformer;
use App\Domain\Gallery\Data\ImageVariantDefinition;
use App\Domain\Gallery\Data\TransformedRasterImage;
use App\Domain\SiteMedia\Actions\CreateSiteMedia;
use App\Domain\SiteMedia\Actions\MarkSiteMediaForRepair;
use App\Domain\SiteMedia\Actions\RegenerateSiteMedia;
use App\Domain\SiteMedia\Actions\UploadSiteMedia;
use App\Domain\SiteMedia\Data\SiteMediaMetadata;
use App\Domain\SiteMedia\Enums\SiteMediaPurpose;
use App\Domain\SiteMedia\Models\SiteMediaAudit;
use App\Domain\SiteMedia\SiteMediaPresenter;
use App\Domain\SiteMedia\Support\SiteMediaProcessingProfiles;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;
use TypeError;

final class PurposeAwareSiteMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config()->set('gallery.photos.disk', 'local');
    }

    public function test_library_facade_retains_capability_variant_and_lifecycle_contracts(): void
    {
        $ordinaryUser = User::factory()->create();

        try {
            app(UploadSiteMedia::class)->handle(
                $ordinaryUser,
                $this->rasterUpload('blocked.jpg', 'image/jpeg', 80, 60),
                new SiteMediaMetadata('Blocked upload', false),
            );
            $this->fail('An ordinary user uploaded to the Media library.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('site_media', 0);
            $this->assertSame([], Storage::disk('local')->allFiles('site-media'));
        }

        $manager = User::factory()->create(['is_admin' => true]);
        $media = app(UploadSiteMedia::class)->handle(
            $manager,
            $this->rasterUpload('library.jpg', 'image/jpeg', 80, 60),
            new SiteMediaMetadata('Library photograph', false),
        );

        $this->assertSame(SiteMediaPurpose::Library, $media->purpose);
        $this->assertNull($media->orphaned_at);
        $this->assertSame(['master', 'large', 'medium', 'thumbnail'], array_keys($media->processed_variants));
        $this->assertSame('image/jpeg', $media->mime_type);
        $this->assertSame('uploaded', $media->audits()->sole()->action);
    }

    public function test_internal_creation_applies_server_selected_walk_purpose_without_granting_media_library_access(): void
    {
        $leader = User::factory()->create(['role' => AccountRole::WalkLeader]);

        $media = app(CreateSiteMedia::class)->handle(
            $leader,
            $this->rasterUpload('walk.jpg', 'image/jpeg', 80, 60),
            new SiteMediaMetadata('Walkers following a ridge path', true, 0.25, 0.75),
            SiteMediaPurpose::WalkFeaturedImage,
        );

        $this->assertFalse($leader->hasCapability(ModuleCapability::ManageSiteMedia));
        $this->assertSame(SiteMediaPurpose::WalkFeaturedImage, $media->purpose);
        $this->assertFalse($media->is_decorative);
        $this->assertSame('Walkers following a ridge path', $media->alt_text);
        $this->assertNotNull($media->orphaned_at);
        $this->assertSame(['master', 'large', 'medium', 'thumbnail'], array_keys($media->processed_variants));
        $this->assertSame($leader->id, $media->audits()->sole()->actor_user_id);
    }

    public function test_internal_creation_rejects_request_shaped_string_purpose_before_side_effects(): void
    {
        $actor = User::factory()->create();

        $this->expectException(TypeError::class);

        try {
            app(CreateSiteMedia::class)->handle(
                $actor,
                $this->rasterUpload('walk.jpg', 'image/jpeg', 80, 60),
                new SiteMediaMetadata('Walkers following a ridge path', false),
                'site_logo',
            );
        } finally {
            $this->assertDatabaseCount('site_media', 0);
            $this->assertSame([], Storage::disk('local')->allFiles('site-media'));
        }
    }

    public function test_logo_is_decorative_png_and_preserves_transparency(): void
    {
        $actor = User::factory()->create();

        $media = app(CreateSiteMedia::class)->handle(
            $actor,
            $this->transparentPngUpload('logo.png', 64, 64),
            new SiteMediaMetadata('Ignored logo description', false),
            SiteMediaPurpose::SiteLogo,
        );

        $this->assertSame(SiteMediaPurpose::SiteLogo, $media->purpose);
        $this->assertTrue($media->is_decorative);
        $this->assertNull($media->alt_text);
        $this->assertNotNull($media->orphaned_at);
        $this->assertSame(['master', 'large', 'medium', 'thumbnail'], array_keys($media->processed_variants));
        $this->assertSame('image/png', $media->mime_type);

        $contents = Storage::disk('local')->get($media->processed_variants['master']);
        $output = imagecreatefromstring($contents);
        $pixel = imagecolorsforindex($output, imagecolorat($output, 0, 0));
        imagedestroy($output);

        $this->assertSame('image/png', getimagesizefromstring($contents)['mime']);
        $this->assertSame(127, $pixel['alpha']);
    }

    public function test_favicon_requires_square_minimum_geometry_and_emits_only_png_favicon_variant(): void
    {
        $actor = User::factory()->create();

        foreach ([
            ['upload' => $this->rasterUpload('non-square.png', 'image/png', 64, 32), 'message' => 'square'],
            ['upload' => $this->rasterUpload('too-small.png', 'image/png', 31, 31), 'message' => 'at least 32'],
        ] as $case) {
            try {
                app(CreateSiteMedia::class)->handle(
                    $actor,
                    $case['upload'],
                    new SiteMediaMetadata('Ignored', false),
                    SiteMediaPurpose::SiteFavicon,
                );
                $this->fail('Invalid favicon geometry was accepted.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString($case['message'], $exception->errors()['photo'][0]);
            }
        }

        $media = app(CreateSiteMedia::class)->handle(
            $actor,
            $this->rasterUpload('favicon.png', 'image/png', 32, 32),
            new SiteMediaMetadata('Ignored favicon description', false),
            SiteMediaPurpose::SiteFavicon,
        );

        $this->assertTrue($media->is_decorative);
        $this->assertNull($media->alt_text);
        $this->assertSame(['favicon'], array_keys($media->processed_variants));
        $this->assertStringEndsWith('/favicon.png', $media->processed_variants['favicon']);
        $this->assertSame('image/png', $media->mime_type);
    }

    public function test_required_png_codec_failure_is_clear_and_creates_no_partial_media(): void
    {
        $this->app->bind(RasterImageTransformer::class, fn (): RasterImageTransformer => new JpegOnlySiteMediaTransformer);
        $actor = User::factory()->create();

        try {
            app(CreateSiteMedia::class)->handle(
                $actor,
                $this->rasterUpload('logo.png', 'image/png', 64, 64),
                new SiteMediaMetadata(null, true),
                SiteMediaPurpose::SiteLogo,
            );
            $this->fail('Logo processing silently fell back without PNG encoding.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('required image/png output', $exception->getMessage());
        }

        $this->assertDatabaseCount('site_media', 0);
        $this->assertDatabaseCount('site_media_audits', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('site-media'));
    }

    public function test_supported_raster_inputs_and_existing_ingestion_safeguards_apply_to_internal_creation(): void
    {
        $actor = User::factory()->create();
        $transformer = app(RasterImageTransformer::class);
        $accepted = 0;

        foreach ([
            ['name' => 'photo.jpg', 'mime' => 'image/jpeg'],
            ['name' => 'photo.png', 'mime' => 'image/png'],
            ['name' => 'photo.webp', 'mime' => 'image/webp'],
            ['name' => 'photo.avif', 'mime' => 'image/avif'],
        ] as $input) {
            if (! $transformer->supportsInput($input['mime'])) {
                continue;
            }

            $media = app(CreateSiteMedia::class)->handle(
                $actor,
                $this->rasterUpload($input['name'], $input['mime'], 40, 30),
                new SiteMediaMetadata('Safe raster', false),
                SiteMediaPurpose::WalkFeaturedImage,
            );

            $this->assertSame('healthy', $media->health_status);
            $this->assertSame(['master', 'large', 'medium', 'thumbnail'], array_keys($media->processed_variants));
            $accepted++;
        }

        $this->assertGreaterThanOrEqual(2, $accepted);

        foreach ([
            ['upload' => $this->rawUpload('partial.png', 'image/png', $this->rasterContents('image/png', 40, 30), UPLOAD_ERR_PARTIAL), 'message' => 'upload failed'],
            ['upload' => $this->rawUpload('image.svg', 'image/svg+xml', '<svg/>'), 'message' => 'unapproved MIME'],
            ['upload' => $this->rawUpload('image.ico', 'image/x-icon', 'not-an-icon'), 'message' => 'unapproved MIME'],
            ['upload' => $this->rawUpload('image.gif', 'image/gif', base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==', true) ?: ''), 'message' => 'unapproved MIME'],
            ['upload' => $this->rawUpload('broken.png', 'image/png', 'not-an-image'), 'message' => 'decoded'],
            ['upload' => $this->rawUpload('spoofed.jpg', 'image/jpeg', $this->rasterContents('image/png', 40, 30)), 'message' => 'does not match'],
        ] as $input) {
            try {
                app(CreateSiteMedia::class)->handle(
                    $actor,
                    $input['upload'],
                    new SiteMediaMetadata('Unsafe raster', false),
                    SiteMediaPurpose::WalkFeaturedImage,
                );
                $this->fail('An unsafe raster input was accepted.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString($input['message'], $exception->errors()['photo'][0]);
            }
        }
    }

    public function test_unsupported_input_and_unexpected_transformed_output_are_rejected_without_state(): void
    {
        $actor = User::factory()->create();
        $this->app->bind(RasterImageTransformer::class, fn (): RasterImageTransformer => new ControlledSiteMediaTransformer(false));

        try {
            app(CreateSiteMedia::class)->handle(
                $actor,
                $this->rasterUpload('unsupported.png', 'image/png', 40, 30),
                new SiteMediaMetadata('Unsupported raster', false),
                SiteMediaPurpose::WalkFeaturedImage,
            );
            $this->fail('An input without a server decoder was accepted.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('not supported by this server', $exception->errors()['photo'][0]);
        }

        $this->app->bind(RasterImageTransformer::class, fn (): RasterImageTransformer => new ControlledSiteMediaTransformer(true, 'image/webp'));

        try {
            app(CreateSiteMedia::class)->handle(
                $actor,
                $this->rasterUpload('unexpected-output.png', 'image/png', 40, 30),
                new SiteMediaMetadata('Unexpected output', false),
                SiteMediaPurpose::WalkFeaturedImage,
            );
            $this->fail('An unexpected transformed output codec was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('unexpected raster codec', $exception->getMessage());
        }

        $this->assertDatabaseCount('site_media', 0);
        $this->assertDatabaseCount('site_media_audits', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('site-media'));
    }

    public function test_upload_resource_limits_and_unsafe_reserved_paths_remain_enforced(): void
    {
        $actor = User::factory()->create();
        $original = config('gallery.processing');

        foreach ([
            ['key' => 'max_upload_bytes', 'value' => 1, 'message' => 'file-size limit'],
            ['key' => 'max_width', 'value' => 20, 'message' => 'dimension limit'],
            ['key' => 'max_pixels', 'value' => 1, 'message' => 'pixel budget'],
            ['key' => 'max_memory_bytes', 'value' => 1, 'message' => 'memory budget'],
        ] as $limit) {
            config()->set('gallery.processing', array_replace($original, [$limit['key'] => $limit['value']]));

            try {
                app(CreateSiteMedia::class)->handle(
                    $actor,
                    $this->rasterUpload('limited.png', 'image/png', 40, 30),
                    new SiteMediaMetadata('Limited raster', false),
                    SiteMediaPurpose::WalkFeaturedImage,
                );
                $this->fail('A SiteMedia resource limit was bypassed.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString($limit['message'], $exception->errors()['photo'][0]);
            }
        }

        config()->set('gallery.processing', $original);
        $configuration = app(SiteMediaProcessingProfiles::class)->configurationFor(SiteMediaPurpose::WalkFeaturedImage);

        $this->expectException(\InvalidArgumentException::class);
        app(IngestCommunityPhoto::class)->handle(
            $this->rasterUpload('unsafe.png', 'image/png', 40, 30),
            '../outside',
            $configuration,
        );
    }

    public function test_record_or_audit_failure_cleans_the_reserved_namespace_and_rolls_back_state(): void
    {
        $actor = User::factory()->create();
        SiteMediaAudit::creating(static function (): void {
            throw new RuntimeException('Audit persistence failed.');
        });

        try {
            app(CreateSiteMedia::class)->handle(
                $actor,
                $this->rasterUpload('walk.jpg', 'image/jpeg', 80, 60),
                new SiteMediaMetadata('Walkers following a ridge path', false),
                SiteMediaPurpose::WalkFeaturedImage,
            );
            $this->fail('Media remained after its audit failed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit persistence failed.', $exception->getMessage());
        } finally {
            SiteMediaAudit::flushEventListeners();
            SiteMediaAudit::clearBootedModels();
        }

        $this->assertDatabaseCount('site_media', 0);
        $this->assertDatabaseCount('site_media_audits', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('site-media'));
    }

    public function test_health_presentation_and_regeneration_follow_purpose_specific_source_contracts(): void
    {
        $manager = User::factory()->create(['is_admin' => true]);
        $favicon = app(CreateSiteMedia::class)->handle(
            $manager,
            $this->rasterUpload('favicon.png', 'image/png', 32, 32),
            new SiteMediaMetadata(null, true),
            SiteMediaPurpose::SiteFavicon,
        );

        app(MarkSiteMediaForRepair::class)->handle($manager, $favicon);
        $this->assertSame('healthy', $favicon->fresh()->health_status);
        $this->assertNotNull(app(SiteMediaPresenter::class)->present($favicon->fresh(), 'favicon'));
        $this->assertNull(app(SiteMediaPresenter::class)->present($favicon->fresh(), 'master'));

        Storage::disk('local')->delete($favicon->processed_variants['favicon']);
        app(MarkSiteMediaForRepair::class)->handle($manager, $favicon->fresh());
        $this->assertSame('repair_required', $favicon->fresh()->health_status);
        $this->assertNull(app(SiteMediaPresenter::class)->present($favicon->fresh(), 'favicon'));

        try {
            app(RegenerateSiteMedia::class)->handle($manager, $favicon->fresh());
            $this->fail('Directly uploaded purpose media promised unavailable regeneration.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('replace or re-upload', strtolower($exception->errors()['media'][0]));
        }
    }

    private function rasterUpload(string $name, string $mimeType, int $width, int $height): UploadedFile
    {
        return $this->rawUpload($name, $mimeType, $this->rasterContents($mimeType, $width, $height));
    }

    private function transparentPngUpload(string $name, int $width, int $height): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagefilledrectangle($image, 8, 8, $width - 9, $height - 9, imagecolorallocatealpha($image, 32, 96, 48, 0));
        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        return $this->rawUpload($name, 'image/png', is_string($contents) ? $contents : '');
    }

    private function rasterContents(string $mimeType, int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 55, 89, 39));
        ob_start();
        $encoded = match ($mimeType) {
            'image/jpeg' => imagejpeg($image, null, 90),
            'image/png' => imagepng($image),
            'image/webp' => imagewebp($image, null, 90),
            'image/avif' => imageavif($image, null, 90),
        };
        $contents = ob_get_clean();
        imagedestroy($image);

        return $encoded && is_string($contents) ? $contents : '';
    }

    private function rawUpload(string $name, string $mimeType, string $contents, int $error = UPLOAD_ERR_OK): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'waymark-site-media-test-');
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, $mimeType, $error, true);
    }
}

final class JpegOnlySiteMediaTransformer implements RasterImageTransformer
{
    public function supportsInput(string $mimeType): bool
    {
        return $mimeType === 'image/png';
    }

    public function supportsOutput(string $mimeType): bool
    {
        return $mimeType === 'image/jpeg';
    }

    public function decode(string $sourcePath, string $mimeType, int $orientation): DecodedRasterImage
    {
        throw new RuntimeException('Required output validation must run before decoding.');
    }

    public function transform(DecodedRasterImage $source, ImageVariantDefinition $variant, string $mimeType): TransformedRasterImage
    {
        throw new RuntimeException('Required output validation must run before transforming.');
    }
}

final class ControlledSiteMediaTransformer implements RasterImageTransformer
{
    public function __construct(
        private readonly bool $supportsInput,
        private readonly string $returnedMimeType = 'image/jpeg',
    ) {}

    public function supportsInput(string $mimeType): bool
    {
        return $this->supportsInput;
    }

    public function supportsOutput(string $mimeType): bool
    {
        return $mimeType === 'image/jpeg';
    }

    public function decode(string $sourcePath, string $mimeType, int $orientation): DecodedRasterImage
    {
        return new class implements DecodedRasterImage
        {
            public function width(): int
            {
                return 40;
            }

            public function height(): int
            {
                return 30;
            }

            public function release(): void {}
        };
    }

    public function transform(DecodedRasterImage $source, ImageVariantDefinition $variant, string $mimeType): TransformedRasterImage
    {
        return new TransformedRasterImage('transformed-raster', 40, 30, $this->returnedMimeType);
    }
}
