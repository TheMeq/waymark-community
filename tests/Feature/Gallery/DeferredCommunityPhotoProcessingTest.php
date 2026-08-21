<?php

namespace Tests\Feature\Gallery;

use App\Domain\Events\Models\Event;
use App\Domain\Gallery\Actions\ProcessDeferredCommunityPhotos;
use App\Domain\Gallery\Contracts\DecodedRasterImage;
use App\Domain\Gallery\Contracts\ImageMetadataReader;
use App\Domain\Gallery\Contracts\RasterImageTransformer;
use App\Domain\Gallery\Data\ImageMetadata;
use App\Domain\Gallery\Data\ImageVariantDefinition;
use App\Domain\Gallery\Data\ProcessedCommunityPhoto;
use App\Domain\Gallery\Data\ProcessedPhotoVariant;
use App\Domain\Gallery\Data\TransformedRasterImage;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\Models\CommunityPhotoProcessingJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class DeferredCommunityPhotoProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_queued_job_finalises_the_photo_and_removes_its_private_staged_source(): void
    {
        Storage::fake('local');
        $this->useProcessorDouble();
        [$photo, $job] = $this->queuedJob();

        $processed = app(ProcessDeferredCommunityPhotos::class)->handle(1);

        $this->assertSame(1, $processed);
        $this->assertSame('complete', $photo->fresh()->processing_status);
        $this->assertNotEmpty($photo->fresh()->processed_variants);
        $this->assertSame('completed', $job->fresh()->status);
        $this->assertNull($job->fresh()->staged_source_path);
        Storage::disk('local')->assertMissing($photo->source_path);
    }

    public function test_a_failed_job_is_retried_after_its_backoff_without_deleting_its_staged_source(): void
    {
        Storage::fake('local');
        config()->set('gallery.deferred.max_attempts', 3);
        config()->set('gallery.deferred.retry_delay_seconds', 60);
        [$photo, $job] = $this->queuedJob('not a photo');

        app(ProcessDeferredCommunityPhotos::class)->handle(1);

        $this->assertSame('retry', $photo->fresh()->processing_status);
        $this->assertSame('retry', $job->fresh()->status);
        $this->assertSame(1, $job->fresh()->attempts);
        $this->assertTrue($job->fresh()->available_at->isAfter(now()->addSeconds(50)));
        $this->assertNotNull($job->fresh()->failure_reason);
        Storage::disk('local')->assertExists($photo->source_path);
    }

    public function test_the_attempt_cap_marks_a_photo_terminal_failed_and_removes_the_staged_source(): void
    {
        Storage::fake('local');
        config()->set('gallery.deferred.max_attempts', 1);
        [$photo, $job] = $this->queuedJob('not a photo');

        app(ProcessDeferredCommunityPhotos::class)->handle(1);
        app(ProcessDeferredCommunityPhotos::class)->handle(1);

        $this->assertSame('failed', $photo->fresh()->processing_status);
        $this->assertSame('terminal_failed', $job->fresh()->status);
        $this->assertSame(1, $job->fresh()->attempts);
        $this->assertNull($job->fresh()->staged_source_path);
        Storage::disk('local')->assertMissing($photo->source_path);
    }

    public function test_stale_processing_claim_recovery_is_bounded_by_the_processor_limit(): void
    {
        Storage::fake('local');
        config()->set('gallery.deferred.max_attempts', 3);
        [$firstPhoto, $first] = $this->queuedJob('not a photo');
        [$secondPhoto, $second] = $this->queuedJob('not a photo');
        foreach ([$first, $second] as $job) {
            $job->update([
                'status' => 'processing', 'attempts' => 1,
                'claimed_at' => now()->subMinutes(20), 'lease_expires_at' => now()->subMinute(), 'claim_token' => 'stale-'.$job->id,
            ]);
            $job->photo->update(['processing_status' => 'processing']);
        }

        $handled = app(ProcessDeferredCommunityPhotos::class)->handle(1);

        $this->assertSame(1, $handled);
        $this->assertSame('retry', $first->fresh()->status);
        $this->assertSame('processing', $second->fresh()->status);
        $this->assertSame('retry', $firstPhoto->fresh()->processing_status);
        $this->assertSame('processing', $secondPhoto->fresh()->processing_status);
    }

    public function test_a_completed_job_cannot_be_processed_twice_by_manual_or_scheduler_invocation(): void
    {
        Storage::fake('local');
        $this->useProcessorDouble();
        [, $job] = $this->queuedJob();
        $processor = app(ProcessDeferredCommunityPhotos::class);

        $this->assertTrue($processor->process($job->id));
        $this->assertFalse($processor->process($job->id));

        $this->assertSame(1, $job->fresh()->attempts);
        $this->assertSame('completed', $job->fresh()->status);
    }

    public function test_a_stale_claim_token_cannot_finalise_or_fail_a_reclaimed_job(): void
    {
        Storage::fake('local');
        [$photo, $job] = $this->queuedJob();
        $job->update(['status' => 'processing', 'claim_token' => 'new-claim', 'output_directory' => 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c9999']);
        $photo->update(['processing_status' => 'processing']);
        $processed = new ProcessedCommunityPhoto(null, [
            'master' => new ProcessedPhotoVariant('community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c9999/master.jpg', 'image/jpeg', 1, 1, 1),
        ], 1, 1, null);
        $processor = app(ProcessDeferredCommunityPhotos::class);

        $this->assertFalse($processor->finalise($job->id, 'old-claim', $processed));
        $this->assertFalse($processor->fail($job->id, 'old-claim'));
        $this->assertSame('processing', $job->fresh()->status);
        $this->assertSame('new-claim', $job->fresh()->claim_token);
    }

    public function test_streamed_disk_sources_are_copied_to_a_temporary_file_and_cleaned_after_processing(): void
    {
        Storage::fake('s3');
        config()->set('gallery.photos.disk', 's3');
        $transformer = new DeferredProcessorRasterTransformer;
        app()->bind(RasterImageTransformer::class, fn (): DeferredProcessorRasterTransformer => $transformer);
        app()->bind(ImageMetadataReader::class, fn (): DeferredProcessorMetadataReader => new DeferredProcessorMetadataReader);
        [, $job] = $this->queuedJob();

        app(ProcessDeferredCommunityPhotos::class)->handle(1);

        $this->assertNotNull($transformer->decodedPath);
        $this->assertFalse(is_file($transformer->decodedPath));
        $this->assertSame('completed', $job->fresh()->status);
    }

    public function test_a_failed_job_does_not_prevent_a_later_queued_job_in_the_bounded_batch(): void
    {
        Storage::fake('local');
        $this->useProcessorDouble();
        [, $failed] = $this->queuedJob('not a photo');
        [, $successful] = $this->queuedJob();

        $processed = app(ProcessDeferredCommunityPhotos::class)->handle(2);

        $this->assertSame(2, $processed);
        $this->assertSame('retry', $failed->fresh()->status);
        $this->assertSame('completed', $successful->fresh()->status);
    }

    public function test_finalisation_failure_removes_partial_derivatives_without_removing_the_retryable_staged_source(): void
    {
        Storage::fake('local');
        $this->useProcessorDouble();
        [$photo, $job] = $this->queuedJob();
        $failFinalisation = true;
        CommunityPhoto::updating(function (CommunityPhoto $updatingPhoto) use (&$failFinalisation): void {
            if ($failFinalisation && $updatingPhoto->processing_status === 'complete') {
                $failFinalisation = false;
                throw new \RuntimeException('Finalisation failed.');
            }
        });

        app(ProcessDeferredCommunityPhotos::class)->handle(1);

        $this->assertSame('retry', $photo->fresh()->processing_status);
        $this->assertSame('retry', $job->fresh()->status);
        Storage::disk('local')->assertExists($photo->source_path);
        $this->assertSame([$photo->source_path], Storage::disk('local')->allFiles('community-photos'));
    }

    public function test_the_cron_command_and_manual_processor_share_the_bounded_job_path(): void
    {
        Storage::fake('local');
        $this->useProcessorDouble();
        [, $job] = $this->queuedJob();

        $this->artisan('gallery:process-deferred-photos', ['--limit' => 1])
            ->expectsOutput('1 photo processing job(s) handled.')
            ->assertSuccessful();

        $this->assertSame('completed', $job->fresh()->status);
    }

    /** @return array{CommunityPhoto, CommunityPhotoProcessingJob} */
    private function queuedJob(string $contents = ''): array
    {
        $contents = $contents === '' ? base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL82QAAAABJRU5ErkJggg==', true) : $contents;
        $directory = 'community-photos/3f2504e0-4f89-41d3-9a0c-0305e82c'.str_pad((string) (CommunityPhoto::query()->count() + 1), 4, '0', STR_PAD_LEFT);
        $path = $directory.'/staged.png';
        Storage::disk((string) config('gallery.photos.disk'))->put($path, $contents);
        $photo = CommunityPhoto::query()->create([
            'event_id' => Event::factory()->create()->id,
            'uploader_id' => User::factory()->create()->id,
            'media_type' => 'image', 'processing_status' => 'queued',
            'storage_disk' => (string) config('gallery.photos.disk'), 'source_path' => $path,
            'processed_variants' => [], 'moderation_status' => 'pending',
        ]);
        $job = CommunityPhotoProcessingJob::query()->create([
            'community_photo_id' => $photo->id, 'status' => 'queued', 'attempts' => 0,
            'staged_source_path' => $path, 'available_at' => now(),
        ]);

        return [$photo, $job];
    }

    private function useProcessorDouble(): void
    {
        app()->bind(RasterImageTransformer::class, fn (): DeferredProcessorRasterTransformer => new DeferredProcessorRasterTransformer);
        app()->bind(ImageMetadataReader::class, fn (): DeferredProcessorMetadataReader => new DeferredProcessorMetadataReader);
    }
}

final class DeferredProcessorRasterTransformer implements RasterImageTransformer
{
    public ?string $decodedPath = null;

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
        $this->decodedPath = $sourcePath;

        return new DeferredProcessorDecodedRaster;
    }

    public function transform(DecodedRasterImage $source, ImageVariantDefinition $variant, string $mimeType): TransformedRasterImage
    {
        return new TransformedRasterImage('safe-raster', 1, 1, $mimeType);
    }
}

final class DeferredProcessorDecodedRaster implements DecodedRasterImage
{
    public function width(): int
    {
        return 1;
    }

    public function height(): int
    {
        return 1;
    }

    public function release(): void {}
}

final class DeferredProcessorMetadataReader implements ImageMetadataReader
{
    public function read(string $path, string $mimeType): ImageMetadata
    {
        return new ImageMetadata(1, null);
    }
}
