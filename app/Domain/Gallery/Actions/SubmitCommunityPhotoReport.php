<?php

namespace App\Domain\Gallery\Actions;

use App\Domain\Gallery\Data\PhotoStorageReference;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\Models\CommunityPhotoReport;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class SubmitCommunityPhotoReport
{
    /** @var list<string> */
    public const REASONS = ['in_photo', 'privacy', 'copyright', 'inappropriate', 'other'];

    public function handle(CommunityPhoto $photo, string $reason, ?string $detail = null, ?string $contact = null, ?User $reporter = null): CommunityPhotoReport
    {
        $this->assertReportable($photo);
        if (! in_array($reason, self::REASONS, true)) {
            throw ValidationException::withMessages(['reason' => 'Choose a report reason.']);
        }
        $detail = $this->text($detail, 1000, 'detail');
        if ($reason === 'other' && $detail === null) {
            throw ValidationException::withMessages(['detail' => 'Please tell us what needs review.']);
        }

        $contact = $this->text($contact, 255, 'contact');
        if ($contact !== null && filter_var($contact, FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::withMessages(['contact' => 'Enter a valid email address.']);
        }

        return CommunityPhotoReport::query()->create([
            'community_photo_id' => $photo->id, 'reporter_user_id' => $reporter?->id,
            'reason' => $reason, 'status' => 'open', 'contact' => $contact,
            'detail' => $detail, 'context_snapshot' => $this->context($photo),
        ]);
    }

    public function isReportable(CommunityPhoto $photo): bool
    {
        try {
            $this->assertReportable($photo);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    private function assertReportable(CommunityPhoto $photo): void
    {
        $path = $photo->processed_variants['master'] ?? null;
        if ($photo->moderation_status !== 'approved' || $photo->published_at === null || $photo->processing_status !== 'complete'
            || ! is_string($path) || ! PhotoStorageReference::isSafe($photo->storage_disk, $path) || ! Storage::disk($photo->storage_disk)->exists($path)) {
            throw ValidationException::withMessages(['photo' => 'This photo is not available for reporting.']);
        }
    }

    private function text(?string $value, int $max, string $field): ?string
    {
        $value = $value === null ? null : trim($value);
        if ($value !== null && mb_strlen($value) > $max) {
            throw ValidationException::withMessages([$field => 'This value is too long.']);
        }

        return $value === '' ? null : $value;
    }

    /** @return array<string, int|string|null> */
    private function context(CommunityPhoto $photo): array
    {
        return ['photo_id' => $photo->id, 'event_id' => $photo->event_id, 'special_album_id' => $photo->special_album_id, 'caption' => $photo->caption];
    }
}
