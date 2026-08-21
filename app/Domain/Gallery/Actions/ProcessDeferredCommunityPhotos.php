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
use Illuminate\Support\Str;
use Throwable;

final readonly class ProcessDeferredCommunityPhotos
{
    public function __construct(private IngestCommunityPhoto $ingest) {}

    public function handle(int $limit = 25): int
    {
        $limit = max(1, min($limit, 100));
        $handled = $this->cleanUp($limit);
        $handled += $this->recoverStaleClaims($limit - $handled);
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
            $worked = DB::transaction(function () use ($job, &$temporaryPath, &$processed): bool {
                $locked = CommunityPhotoProcessingJob::query()->lockForUpdate()->findOrFail($job->id);
                $photo = CommunityPhoto::query()->lockForUpdate()->findOrFail($locked->community_photo_id);

                if ($locked->status !== 'processing' || $locked->claim_token !== $job->claim_token || ! $this->hasSafeStagedSource($locked, $photo)) {
                    return false;
                }

                $locked->setRelation('photo', $photo);
                $temporaryPath = $this->copyStagedSourceToTemporaryFile($locked);
                $processed = $this->ingest->handle($this->temporaryUpload($temporaryPath), $locked->output_directory);

                return $this->finalise($locked->id, $locked->claim_token, $processed);
            });

            if (! $worked) {
                return false;
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->fail($job->id, $job->claim_token);
            $this->cleanUp(1);

            return true;
        } finally {
            if (is_string($temporaryPath) && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }

        $this->cleanUp(1);

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

            if (! $photo instanceof CommunityPhoto || ! $this->hasSafeStagedSource($job, $photo) || $job->output_directory !== null) {
                return null;
            }

            $job->update([
                'status' => 'processing', 'attempts' => $job->attempts + 1,
                'claimed_at' => now(), 'lease_expires_at' => now()->addMinutes($this->leaseMinutes()),
                'claim_token' => Str::uuid()->toString(),
                'output_directory' => trim((string) config('gallery.photos.directory', 'community-photos'), '/').'/'.Str::uuid()->toString(),
                'failure_reason' => null,
            ]);
            $photo->update(['processing_status' => 'processing']);

            return $job->fresh(['photo']);
        });
    }

    public function finalise(int $jobId, ?string $claimToken, ProcessedCommunityPhoto $processed): bool
    {
        return DB::transaction(function () use ($jobId, $claimToken, $processed): bool {
            $job = CommunityPhotoProcessingJob::query()->lockForUpdate()->findOrFail($jobId);
            $photo = CommunityPhoto::query()->lockForUpdate()->findOrFail($job->community_photo_id);

            if ($job->status !== 'processing' || $job->claim_token !== $claimToken || ! $this->hasSafeStagedSource($job, $photo)) {
                return false;
            }

            $photo->update([
                'processing_status' => 'complete',
                'source_path' => $this->sourcePath($processed),
                'processed_variants' => $this->variantPaths($processed),
                'width' => $processed->width, 'height' => $processed->height,
                'file_size_bytes' => $this->sourceFileSize($processed), 'captured_at' => $processed->capturedAt,
            ]);
            $job->update([
                'status' => 'completed', 'available_at' => null,
                'claimed_at' => null, 'lease_expires_at' => null, 'failure_reason' => null,
                'claim_token' => null, 'output_directory' => null,
            ]);

            return true;
        });
    }

    public function fail(int $jobId, ?string $claimToken): bool
    {
        return DB::transaction(function () use ($jobId, $claimToken): bool {
            $job = CommunityPhotoProcessingJob::query()->lockForUpdate()->find($jobId);

            if (! $job instanceof CommunityPhotoProcessingJob || $job->status !== 'processing') {
                return false;
            }

            $photo = CommunityPhoto::query()->lockForUpdate()->find($job->community_photo_id);

            if (! $photo instanceof CommunityPhoto || $job->claim_token !== $claimToken || ! $this->hasSafeStagedSource($job, $photo)) {
                return false;
            }

            if ($job->attempts >= $this->maxAttempts()) {
                $photo->update(['processing_status' => 'failed', 'processed_variants' => []]);
                $job->update([
                    'status' => 'terminal_failed', 'available_at' => null,
                    'claimed_at' => null, 'lease_expires_at' => null,
                    'claim_token' => null,
                    'failure_reason' => 'Photo processing failed. It needs a manual retry.',
                ]);

                return true;
            }

            $photo->update(['processing_status' => 'retry']);
            $job->update([
                'status' => 'retry', 'available_at' => now()->addSeconds($this->backoffSeconds($job->attempts)),
                'claimed_at' => null, 'lease_expires_at' => null,
                'claim_token' => null,
                'failure_reason' => 'Photo processing failed. It will be retried automatically.',
            ]);

            return true;
        });
    }

    private function recoverStaleClaims(int $limit): int
    {
        $recovered = 0;
        CommunityPhotoProcessingJob::query()->where('status', 'processing')->where('lease_expires_at', '<=', now())
            ->orderBy('id')->limit($limit)->pluck('id')->each(function (int $jobId) use (&$recovered): void {
                try {
                    $token = CommunityPhotoProcessingJob::query()->whereKey($jobId)->value('claim_token');
                    if (is_string($token) && $this->fail($jobId, $token)) {
                        $recovered++;
                    }
                } catch (Throwable $exception) {
                    report($exception);
                }
            });

        return $recovered;
    }

    private function cleanUp(int $limit): int
    {
        if ($limit < 1) {
            return 0;
        }

        $handled = 0;
        CommunityPhotoProcessingJob::query()->where('status', 'staging')->where('staging_lease_expires_at', '<=', now())->orderBy('id')->limit($limit)->pluck('id')
            ->each(function (int $jobId) use (&$handled): void {
                if ($this->recoverStaging($jobId)) {
                    $handled++;
                }
            });
        if ($handled >= $limit) {
            return $handled;
        }

        CommunityPhotoProcessingJob::query()->whereNotNull('output_directory')->where('status', '!=', 'processing')->orderBy('id')->limit($limit - $handled)->pluck('id')
            ->each(function (int $jobId) use (&$handled): void {
                if ($this->deleteOutputDirectory($jobId)) {
                    $handled++;
                }
            });
        if ($handled >= $limit) {
            return $handled;
        }

        CommunityPhotoProcessingJob::query()->whereIn('status', ['completed', 'terminal_failed'])->whereNotNull('staged_source_path')->orderBy('id')->limit($limit - $handled)->pluck('id')
            ->each(function (int $jobId) use (&$handled): void {
                if ($this->deleteStagedSource($jobId)) {
                    $handled++;
                }
            });

        return $handled;
    }

    private function recoverStaging(int $jobId): bool
    {
        return DB::transaction(function () use ($jobId): bool {
            $job = CommunityPhotoProcessingJob::query()->lockForUpdate()->find($jobId);
            if (! $job instanceof CommunityPhotoProcessingJob || $job->status !== 'staging') {
                return false;
            }
            $photo = CommunityPhoto::query()->lockForUpdate()->find($job->community_photo_id);
            if (! $photo instanceof CommunityPhoto || ! is_string($job->staged_source_path)) {
                return false;
            }
            if (Storage::disk($photo->storage_disk)->exists($job->staged_source_path)) {
                $job->update(['status' => 'queued', 'available_at' => now(), 'staging_lease_expires_at' => null]);
                $photo->update(['processing_status' => 'queued']);
            } else {
                $job->update(['status' => 'terminal_failed', 'staged_source_path' => null, 'staging_lease_expires_at' => null, 'failure_reason' => 'Photo staging did not complete.']);
                $photo->update(['processing_status' => 'failed']);
            }

            return true;
        });
    }

    private function deleteOutputDirectory(int $jobId): bool
    {
        $job = CommunityPhotoProcessingJob::query()->with('photo')->find($jobId);
        if (! $job instanceof CommunityPhotoProcessingJob || ! $job->photo instanceof CommunityPhoto || ! is_string($job->output_directory)) {
            return false;
        }
        $disk = Storage::disk($job->photo->storage_disk);
        if ($disk->exists($job->output_directory)) {
            if (! $disk->deleteDirectory($job->output_directory)) {
                return false;
            }
        }

        return DB::transaction(function () use ($job): bool {
            $locked = CommunityPhotoProcessingJob::query()->lockForUpdate()->find($job->id);
            if (! $locked instanceof CommunityPhotoProcessingJob || $locked->output_directory !== $job->output_directory) {
                return false;
            }
            $locked->update(['output_directory' => null]);

            return true;
        });
    }

    private function deleteStagedSource(int $jobId): bool
    {
        $job = CommunityPhotoProcessingJob::query()->with('photo')->find($jobId);
        if (! $job instanceof CommunityPhotoProcessingJob || ! $job->photo instanceof CommunityPhoto || ! is_string($job->staged_source_path)) {
            return false;
        }
        $disk = Storage::disk($job->photo->storage_disk);
        if ($disk->exists($job->staged_source_path) && ! $disk->delete($job->staged_source_path)) {
            return false;
        }

        return DB::transaction(function () use ($job): bool {
            $locked = CommunityPhotoProcessingJob::query()->lockForUpdate()->find($job->id);
            if (! $locked instanceof CommunityPhotoProcessingJob || $locked->staged_source_path !== $job->staged_source_path) {
                return false;
            }
            $locked->update(['staged_source_path' => null]);

            return true;
        });
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
