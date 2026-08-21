<?php

namespace App\Domain\Gallery\Actions;

use App\Domain\Gallery\Data\PhotoStorageReference;
use App\Domain\Gallery\Data\ProcessedCommunityPhoto;
use App\Domain\Gallery\Data\ProcessedPhotoVariant;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\Models\CommunityPhotoProcessingJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final readonly class ProcessDeferredCommunityPhotos
{
    public function __construct(private IngestCommunityPhoto $ingest) {}

    public function handle(int $limit = 25): int
    {
        $limit = max(1, min($limit, 100));
        $handled = $this->recoverStaleClaims($limit);
        $remaining = $limit - $handled;

        if ($remaining < 1) {
            return $handled;
        }

        CommunityPhotoProcessingJob::query()
            ->whereIn('status', ['queued', 'retry'])
            ->where(fn ($query) => $query->whereNull('available_at')->orWhere('available_at', '<=', now()))
            ->orderBy('id')
            ->limit($remaining)
            ->pluck('id')
            ->each(function (int $jobId) use (&$handled): void {
                if ($this->process($jobId)) {
                    $handled++;
                }
            });

        return $handled;
    }

    public function process(int $jobId): bool
    {
        $job = $this->claim($jobId);

        if (! $job instanceof CommunityPhotoProcessingJob) {
            return false;
        }

        $temporaryPath = null;
        $processed = null;

        try {
            $temporaryPath = $this->copyStagedSourceToTemporaryFile($job);
            $processed = $this->ingest->handle($this->temporaryUpload($temporaryPath));
            $this->finalise($job->id, $processed);
        } catch (Throwable $exception) {
            if ($processed instanceof ProcessedCommunityPhoto) {
                $this->cleanProcessedDirectory($job, $processed);
            }

            report($exception);
            $this->fail($job->id);

            return true;
        } finally {
            if (is_string($temporaryPath) && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }

        try {
            Storage::disk($job->photo->storage_disk)->delete($job->staged_source_path);
        } catch (Throwable $exception) {
            report($exception);
        }

        return true;
    }

    private function claim(int $jobId): ?CommunityPhotoProcessingJob
    {
        return DB::transaction(function () use ($jobId): ?CommunityPhotoProcessingJob {
            $job = CommunityPhotoProcessingJob::query()->lockForUpdate()->find($jobId);

            if (! $job instanceof CommunityPhotoProcessingJob
                || ! in_array($job->status, ['queued', 'retry'], true)
                || ($job->available_at !== null && $job->available_at->isFuture())) {
                return null;
            }

            $photo = CommunityPhoto::query()->lockForUpdate()->find($job->community_photo_id);

            if (! $photo instanceof CommunityPhoto || ! $this->hasSafeStagedSource($job, $photo)) {
                return null;
            }

            $job->update([
                'status' => 'processing', 'attempts' => $job->attempts + 1,
                'claimed_at' => now(), 'lease_expires_at' => now()->addMinutes($this->leaseMinutes()),
                'failure_reason' => null,
            ]);
            $photo->update(['processing_status' => 'processing']);

            return $job->fresh(['photo']);
        });
    }

    private function finalise(int $jobId, ProcessedCommunityPhoto $processed): void
    {
        DB::transaction(function () use ($jobId, $processed): void {
            $job = CommunityPhotoProcessingJob::query()->lockForUpdate()->findOrFail($jobId);
            $photo = CommunityPhoto::query()->lockForUpdate()->findOrFail($job->community_photo_id);

            if ($job->status !== 'processing' || ! $this->hasSafeStagedSource($job, $photo)) {
                throw new \RuntimeException('The photo processing claim is no longer current.');
            }

            $photo->update([
                'processing_status' => 'complete',
                'source_path' => $this->sourcePath($processed),
                'processed_variants' => $this->variantPaths($processed),
                'width' => $processed->width, 'height' => $processed->height,
                'file_size_bytes' => $this->sourceFileSize($processed), 'captured_at' => $processed->capturedAt,
            ]);
            $job->update([
                'status' => 'completed', 'staged_source_path' => null, 'available_at' => null,
                'claimed_at' => null, 'lease_expires_at' => null, 'failure_reason' => null,
            ]);
        });
    }

    private function fail(int $jobId): void
    {
        $terminalSource = DB::transaction(function () use ($jobId): ?array {
            $job = CommunityPhotoProcessingJob::query()->lockForUpdate()->find($jobId);

            if (! $job instanceof CommunityPhotoProcessingJob || $job->status !== 'processing') {
                return null;
            }

            $photo = CommunityPhoto::query()->lockForUpdate()->find($job->community_photo_id);

            if (! $photo instanceof CommunityPhoto || ! $this->hasSafeStagedSource($job, $photo)) {
                return null;
            }

            if ($job->attempts >= $this->maxAttempts()) {
                $path = $job->staged_source_path;
                $disk = $photo->storage_disk;
                $photo->update(['processing_status' => 'failed', 'processed_variants' => []]);
                $job->update([
                    'status' => 'terminal_failed', 'staged_source_path' => null, 'available_at' => null,
                    'claimed_at' => null, 'lease_expires_at' => null,
                    'failure_reason' => 'Photo processing failed. It needs a manual retry.',
                ]);

                return [$disk, $path];
            }

            $photo->update(['processing_status' => 'retry']);
            $job->update([
                'status' => 'retry', 'available_at' => now()->addSeconds($this->backoffSeconds($job->attempts)),
                'claimed_at' => null, 'lease_expires_at' => null,
                'failure_reason' => 'Photo processing failed. It will be retried automatically.',
            ]);

            return null;
        });

        if (is_array($terminalSource)) {
            try {
                Storage::disk($terminalSource[0])->delete($terminalSource[1]);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    private function recoverStaleClaims(int $limit): int
    {
        $recovered = 0;
        CommunityPhotoProcessingJob::query()->where('status', 'processing')->where('lease_expires_at', '<=', now())
            ->orderBy('id')->limit($limit)->pluck('id')->each(function (int $jobId) use (&$recovered): void {
                try {
                    $this->fail($jobId);
                    $recovered++;
                } catch (Throwable $exception) {
                    report($exception);
                }
            });

        return $recovered;
    }

    private function copyStagedSourceToTemporaryFile(CommunityPhotoProcessingJob $job): string
    {
        $photo = $job->photo;

        if (! $photo instanceof CommunityPhoto || ! $this->hasSafeStagedSource($job, $photo)) {
            throw new \RuntimeException('The staged photo source is invalid.');
        }

        $input = Storage::disk($photo->storage_disk)->readStream($job->staged_source_path);

        if (! is_resource($input)) {
            throw new \RuntimeException('The staged photo source could not be read.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'waymark-photo-');

        if (! is_string($temporaryPath)) {
            fclose($input);
            throw new \RuntimeException('A temporary photo-processing file could not be created.');
        }

        $output = fopen($temporaryPath, 'wb');

        if (! is_resource($output)) {
            fclose($input);
            @unlink($temporaryPath);
            throw new \RuntimeException('A temporary photo-processing file could not be opened.');
        }

        try {
            $copied = 0;
            $maximum = (int) config('gallery.processing.max_upload_bytes', 0);

            while (! feof($input)) {
                $chunk = fread($input, 8192);
                if ($chunk === false) {
                    throw new \RuntimeException('The staged photo source could not be copied.');
                }
                $copied += strlen($chunk);
                if ($maximum > 0 && $copied > $maximum) {
                    throw new \RuntimeException('The staged photo source exceeds the file-size limit.');
                }
                if ($chunk !== '' && fwrite($output, $chunk) === false) {
                    throw new \RuntimeException('The temporary photo-processing file could not be written.');
                }
            }
        } catch (Throwable $exception) {
            fclose($input);
            fclose($output);
            @unlink($temporaryPath);
            throw $exception;
        } finally {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }
        }

        return $temporaryPath;
    }

    private function temporaryUpload(string $path): UploadedFile
    {
        return new UploadedFile($path, basename($path).'.png', mime_content_type($path) ?: null, UPLOAD_ERR_OK, true);
    }

    private function cleanProcessedDirectory(CommunityPhotoProcessingJob $job, ProcessedCommunityPhoto $processed): void
    {
        try {
            Storage::disk($job->photo->storage_disk)->deleteDirectory(dirname($this->sourcePath($processed)));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function hasSafeStagedSource(CommunityPhotoProcessingJob $job, CommunityPhoto $photo): bool
    {
        return is_string($job->staged_source_path)
            && $job->staged_source_path === $photo->source_path
            && PhotoStorageReference::isSafe($photo->storage_disk, $job->staged_source_path);
    }

    private function maxAttempts(): int
    {
        return max(1, (int) config('gallery.deferred.max_attempts', 3));
    }

    private function leaseMinutes(): int
    {
        return max(1, (int) config('gallery.deferred.lease_minutes', 15));
    }

    private function backoffSeconds(int $attempt): int
    {
        return min(3600, max(1, (int) config('gallery.deferred.retry_delay_seconds', 60)) * (2 ** max(0, $attempt - 1)));
    }

    private function sourcePath(ProcessedCommunityPhoto $processed): string
    {
        return ($processed->retainedSource ?? $processed->variants['master'])->path;
    }

    /** @return array<string, string> */
    private function variantPaths(ProcessedCommunityPhoto $processed): array
    {
        return collect($processed->variants)->map(fn (ProcessedPhotoVariant $variant) => $variant->path)->all();
    }

    private function sourceFileSize(ProcessedCommunityPhoto $processed): int
    {
        return ($processed->retainedSource ?? $processed->variants['master'])->fileSizeBytes;
    }
}
