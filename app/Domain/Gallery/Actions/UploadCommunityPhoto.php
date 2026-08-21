<?php

namespace App\Domain\Gallery\Actions;

use App\Domain\Events\Models\Event;
use App\Domain\Gallery\Data\ProcessedCommunityPhoto;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\Models\SpecialAlbum;
use App\Domain\Gallery\PhotoUploadPolicyDecision;
use App\Domain\Gallery\PhotoUploadPolicyGate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final readonly class UploadCommunityPhoto
{
    public function __construct(
        private IngestCommunityPhoto $ingest,
        private PhotoUploadPolicyGate $policyGate,
        private AcceptCurrentPhotoUploadPolicy $acceptPolicy,
    ) {}

    public function handle(
        User $account,
        UploadedFile $upload,
        string $context,
        ?string $photographerName,
        ?string $caption,
        bool $acceptCurrentPolicy,
    ): CommunityPhoto {
        $this->ensureUploadAllowed($account, $acceptCurrentPolicy);
        [$event, $specialAlbum] = $this->resolveContext($context);
        $processed = $this->ingest->handle($upload);

        try {
            return DB::transaction(fn (): CommunityPhoto => CommunityPhoto::query()->create([
                'event_id' => $event?->id,
                'special_album_id' => $specialAlbum?->id,
                'uploader_id' => $account->id,
                'media_type' => 'image',
                'processing_status' => 'complete',
                'storage_disk' => (string) config('gallery.photos.disk', 'local'),
                'source_path' => $this->sourcePath($processed),
                'processed_variants' => $this->variantPaths($processed),
                'width' => $processed->width,
                'height' => $processed->height,
                'file_size_bytes' => $this->sourceFileSize($processed),
                'caption' => $this->nullableTrimmed($caption),
                'photographer_name' => $this->nullableTrimmed($photographerName) ?? $account->publicDisplayName(),
                'moderation_status' => 'pending',
                'captured_at' => $processed->capturedAt,
            ]));
        } catch (\Throwable $exception) {
            Storage::disk((string) config('gallery.photos.disk', 'local'))
                ->deleteDirectory(dirname($this->sourcePath($processed)));

            throw $exception;
        }
    }

    private function ensureUploadAllowed(User $account, bool $acceptCurrentPolicy): void
    {
        $decision = $this->policyGate->for($account);

        if ($decision === PhotoUploadPolicyDecision::UploadAllowedWithReminder) {
            return;
        }

        if (in_array($decision, [
            PhotoUploadPolicyDecision::PolicyAcceptanceRequired,
            PhotoUploadPolicyDecision::PolicyVersionAcceptanceRequired,
        ], true) && $acceptCurrentPolicy) {
            $this->acceptPolicy->handle($account);

            return;
        }

        $message = match ($decision) {
            PhotoUploadPolicyDecision::AccountInactive => 'Only active accounts can upload photos.',
            PhotoUploadPolicyDecision::EmailVerificationRequired => 'Verify your email address before uploading photos.',
            default => 'Accept the current photo policy before uploading photos.',
        };

        throw ValidationException::withMessages(['photo_policy' => $message]);
    }

    /** @return array{?Event, ?SpecialAlbum} */
    private function resolveContext(string $context): array
    {
        if (preg_match('/\A(event|album):([1-9][0-9]*)\z/', $context, $matches) !== 1) {
            throw ValidationException::withMessages(['context' => 'Choose a valid event or special album.']);
        }

        if ($matches[1] === 'event') {
            $event = Event::query()->find($matches[2]);

            if ($event === null) {
                throw ValidationException::withMessages(['context' => 'Choose a valid event or special album.']);
            }

            return [$event, null];
        }

        $specialAlbum = SpecialAlbum::query()->find($matches[2]);

        if ($specialAlbum === null) {
            throw ValidationException::withMessages(['context' => 'Choose a valid event or special album.']);
        }

        return [null, $specialAlbum];
    }

    private function sourcePath(ProcessedCommunityPhoto $processed): string
    {
        return ($processed->retainedSource ?? $processed->variants['master'])->path;
    }

    /** @return array<string, string> */
    private function variantPaths(ProcessedCommunityPhoto $processed): array
    {
        $paths = [];

        foreach ($processed->variants as $name => $variant) {
            $paths[$name] = $variant->path;
        }

        return $paths;
    }

    private function sourceFileSize(ProcessedCommunityPhoto $processed): int
    {
        return ($processed->retainedSource ?? $processed->variants['master'])->fileSizeBytes;
    }

    private function nullableTrimmed(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
