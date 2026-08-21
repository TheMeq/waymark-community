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
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
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

    public function test_completed_raw_cleanup_keeps_its_reference_after_a_false_delete_then_clears_it_after_a_later_success(): void
    {
        Storage::fake('local');
        [$photo, $job] = $this->queuedJob();
        $job->update(['status' => 'completed']);
        $path = $job->staged_source_path;
        $disk = Mockery::mock();
        $disk->shouldReceive('exists')->with($path)->once()->andReturnTrue();
        $disk->shouldReceive('delete')->with($path)->once()->andReturnFalse();
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        app(ProcessDeferredCommunityPhotos::class)->handle(1);

        $this->assertSame($path, $job->fresh()->staged_source_path);

        $this->restoreLocalStorageFake();
        Storage::disk('local')->put($path, 'staged source');
        app(ProcessDeferredCommunityPhotos::class)->handle(1);

        $this->assertNull($job->fresh()->staged_source_path);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_terminal_raw_cleanup_keeps_its_reference_after_a_false_delete_then_clears_an_already_absent_source(): void
    {
        Storage::fake('local');
        [, $job] = $this->queuedJob();
        $path = $job->staged_source_path;
        $job->update(['status' => 'terminal_failed']);
        $disk = Mockery::mock();
        $disk->shouldReceive('exists')->with($path)->once()->andReturnTrue();
        $disk->shouldReceive('delete')->with($path)->once()->andReturnFalse();
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        app(ProcessDeferredCommunityPhotos::class)->handle(1);

        $this->assertSame($path, $job->fresh()->staged_source_path);

        $this->restoreLocalStorageFake();
        app(ProcessDeferredCommunityPhotos::class)->handle(1);

        $this->assertNull($job->fresh()->staged_source_path);
    }

    public function test_failed_output_cleanup_keeps_the_reserved_directory_and_prevents_reclaim_until_later_cleanup(): void
    {
        Storage::fake('local');
        [$photo, $job] = $this->queuedJob();
        $directory = 'community-photos/failed-output';
        Storage::disk('local')->put($directory.'/master.jpg', 'partial derivative');
        $job->update(['status' => 'retry', 'available_at' => now(), 'output_directory' => $directory]);
        $photo->update(['processing_status' => 'retry']);
        $disk = Mockery::mock();
        $disk->shouldReceive('exists')->with($directory)->once()->andReturnTrue();
        $disk->shouldReceive('deleteDirectory')->with($directory)->once()->andReturnFalse();
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        $this->assertSame(0, app(ProcessDeferredCommunityPhotos::class)->handle(1));
        $this->assertSame($directory, $job->fresh()->output_directory);
        $this->assertSame(0, $job->fresh()->attempts);

        $this->restoreLocalStorageFake();
        $job->update(['available_at' => now()->addMinute()]);
        Storage::disk('local')->put($directory.'/master.jpg', 'partial derivative');
        app(ProcessDeferredCommunityPhotos::class)->handle(1);

        $this->assertNull($job->fresh()->output_directory);
        Storage::disk('local')->assertMissing($directory.'/master.jpg');

        $job->update(['output_directory' => 'community-photos/already-absent']);
        app(ProcessDeferredCommunityPhotos::class)->handle(1);

        $this->assertNull($job->fresh()->output_directory);
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

    public function test_stale_recovery_sets_the_mysql_session_timeout_before_bounded_contention_and_continues_later_work(): void
    {
        Storage::fake('local');
        $this->useProcessorDouble();
        config()->set('gallery.deferred.database_lock_wait_seconds', 3);
        [$blockedPhoto, $blocked] = $this->queuedJob();
        [, $successful] = $this->queuedJob();
        $blocked->update([
            'status' => 'processing', 'attempts' => 1,
            'claimed_at' => now()->subMinutes(20), 'lease_expires_at' => now()->subMinute(), 'claim_token' => 'stale-'.$blocked->id,
        ]);
        $blockedPhoto->update(['processing_status' => 'processing']);
        $defaultConnection = DB::getDefaultConnection();
        $connection = $this->useRecordingMySqlConnection();
        $this->assertSame($connection, DB::connection());
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $configuredBeforeContention = false;
        $hasContended = false;

        try {
            CommunityPhoto::updating(function (CommunityPhoto $photo) use ($connection, &$configuredBeforeContention, &$hasContended): void {
                if (! $hasContended && $photo->processing_status === 'retry') {
                    $hasContended = true;
                    $configuredBeforeContention = in_array('SET SESSION innodb_lock_wait_timeout = 3', $connection->unpreparedStatements, true);
                    $previous = new \PDOException('Lock wait timeout exceeded.');
                    $previous->errorInfo = ['HY000', 1205, 'Lock wait timeout exceeded.'];

                    throw new QueryException('mysql', 'update community_photos', [], $previous);
                }
            });

            $handled = app(ProcessDeferredCommunityPhotos::class)->handle(2);

            $this->assertSame('SET SESSION innodb_lock_wait_timeout = 3', $connection->unpreparedStatements[0]);
            $this->assertTrue($configuredBeforeContention);
            $this->assertTrue($hasContended);
            $this->assertSame(1, $handled);
            $this->assertSame('processing', $blocked->fresh()->status);
            $this->assertSame('completed', $successful->fresh()->status);
        } finally {
            DB::setDefaultConnection($defaultConnection);
            DB::purge('recording-mysql');
        }
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

    public function test_an_active_staging_lease_is_not_recovered_by_the_scheduler(): void
    {
        Storage::fake('local');
        [$photo, $job] = $this->queuedJob();
        $photo->update(['processing_status' => 'staging']);
        $job->update(['status' => 'staging', 'staging_lease_expires_at' => now()->addMinute()]);

        $this->assertSame(0, app(ProcessDeferredCommunityPhotos::class)->handle(1));
        $this->assertSame('staging', $job->fresh()->status);
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

    public function test_a_database_lock_contention_returns_safely_and_allows_the_bounded_batch_to_continue(): void
    {
        Storage::fake('local');
        $this->useProcessorDouble();
        [, $blocked] = $this->queuedJob();
        [, $successful] = $this->queuedJob();
        $hasContended = false;
        CommunityPhoto::updating(function (CommunityPhoto $photo) use (&$hasContended): void {
            if (! $hasContended && $photo->processing_status === 'complete') {
                $hasContended = true;
                $previous = new \PDOException('Deadlock found while waiting for a lock.');
                $previous->errorInfo = ['40001', 1213, 'Deadlock found while waiting for a lock.'];

                throw new QueryException('mysql', 'update community_photos', [], $previous);
            }
        });

        $handled = app(ProcessDeferredCommunityPhotos::class)->handle(2);

        $this->assertSame(1, $handled);
        $this->assertSame('processing', $blocked->fresh()->status);
        $this->assertSame(1, $blocked->fresh()->attempts);
        $this->assertSame('completed', $successful->fresh()->status);
        $this->assertFalse(app(ProcessDeferredCommunityPhotos::class)->process($blocked->id));
    }

    public function test_a_single_job_limit_processes_only_that_jobs_cleanup_and_leaves_unrelated_cleanup_pending(): void
    {
        Storage::fake('local');
        $this->useProcessorDouble();
        [$processedPhoto, $processedJob] = $this->queuedJob();
        [$unrelatedPhoto, $unrelatedJob] = $this->queuedJob();
        $unrelatedJob->update(['status' => 'completed']);

        $handled = app(ProcessDeferredCommunityPhotos::class)->handle(1);

        $this->assertSame(1, $handled);
        $this->assertSame('completed', $processedJob->fresh()->status);
        $this->assertNull($processedJob->fresh()->staged_source_path);
        $this->assertSame('completed', $unrelatedJob->fresh()->status);
        $this->assertSame($unrelatedPhoto->source_path, $unrelatedJob->fresh()->staged_source_path);
        Storage::disk('local')->assertExists($unrelatedPhoto->source_path);
        $this->assertSame('complete', $processedPhoto->fresh()->processing_status);
    }

    public function test_the_gallery_schedule_uses_a_short_explicit_overlap_expiry(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'gallery:process-deferred-photos --limit=25'));

        $this->assertNotNull($event);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame((int) config('gallery.deferred.schedule_lock_minutes'), $event->expiresAt);
        $this->assertLessThan(60, $event->expiresAt);
    }

    public function test_finalisation_failure_keeps_the_reserved_output_reference_until_a_later_cleanup(): void
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
        $outputDirectory = $job->fresh()->output_directory;
        $this->assertNotNull($outputDirectory);
        $this->assertNotSame([$photo->source_path], Storage::disk('local')->allFiles('community-photos'));

        app(ProcessDeferredCommunityPhotos::class)->handle(1);

        $this->assertNull($job->fresh()->output_directory);
        $this->assertSame([$photo->source_path], Storage::disk('local')->allFiles('community-photos'));
    }

    public function test_deferred_job_hardening_migrations_roll_back_and_restore_their_fields(): void
    {
        $stagingLease = $this->stagingLeaseMigration();
        $hardening = $this->hardeningMigration();

        $stagingLease->down();
        $hardening->down();

        $this->assertFalse(Schema::hasColumn('community_photo_processing_jobs', 'claim_token'));
        $this->assertFalse(Schema::hasColumn('community_photo_processing_jobs', 'output_directory'));
        $this->assertFalse(Schema::hasColumn('community_photo_processing_jobs', 'staging_lease_expires_at'));

        $hardening->up();
        $stagingLease->up();

        $this->assertTrue(Schema::hasColumn('community_photo_processing_jobs', 'claim_token'));
        $this->assertTrue(Schema::hasColumn('community_photo_processing_jobs', 'output_directory'));
        $this->assertTrue(Schema::hasColumn('community_photo_processing_jobs', 'staging_lease_expires_at'));
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

    private function restoreLocalStorageFake(): void
    {
        $this->app->forgetInstance('filesystem');
        Storage::clearResolvedInstance('filesystem');
        Storage::fake('local');
    }

    private function useRecordingMySqlConnection(): RecordingMySqlConnection
    {
        $original = DB::connection();
        $connection = new RecordingMySqlConnection(
            $original->getPdo(), $original->getDatabaseName(), $original->getTablePrefix(), $original->getConfig(),
        );
        $connection->adoptTransactionLevel($original->transactionLevel());

        DB::extend('recording-mysql', fn (): RecordingMySqlConnection => $connection);
        config()->set('database.connections.recording-mysql', ['driver' => 'recording-mysql']);
        DB::setDefaultConnection('recording-mysql');

        return $connection;
    }

    private function hardeningMigration(): Migration
    {
        return require database_path('migrations/2026_08_21_163000_harden_community_photo_processing_jobs.php');
    }

    private function stagingLeaseMigration(): Migration
    {
        return require database_path('migrations/2026_08_21_164000_add_staging_lease_to_community_photo_processing_jobs.php');
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

final class RecordingMySqlConnection extends SQLiteConnection
{
    /** @var list<string> */
    public array $unpreparedStatements = [];

    public function getDriverName(): string
    {
        return 'mysql';
    }

    public function unprepared($query): bool
    {
        $this->unpreparedStatements[] = $query;

        return true;
    }

    public function adoptTransactionLevel(int $level): void
    {
        $this->transactions = $level;
    }
}
