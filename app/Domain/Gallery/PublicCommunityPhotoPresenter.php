<?php

namespace App\Domain\Gallery;

use App\Domain\Gallery\Data\PhotoStorageReference;
use App\Domain\Gallery\Data\PublicCommunityPhotoPresentation;
use App\Domain\Gallery\Models\CommunityPhoto;
use App\Domain\Gallery\Queries\UploadablePublicEvents;
use Illuminate\Support\Facades\Storage;

final class PublicCommunityPhotoPresenter
{
    /** @var list<string> */
    private const VARIANTS = ['thumbnail', 'medium', 'large', 'master'];

    public function __construct(private readonly UploadablePublicEvents $events) {}

    public function isEligible(CommunityPhoto $photo, string $variant = 'thumbnail', bool $publicContextVerified = false): bool
    {
        return $photo->moderation_status === 'approved'
            && $photo->published_at !== null
            && $photo->published_at->lessThanOrEqualTo(now())
            && $photo->processing_status === 'complete'
            && ($publicContextVerified || $this->hasPublicContext($photo))
            && $this->safeExistingPath($photo, $variant) !== null;
    }

    public function pathFor(CommunityPhoto $photo, string $variant): ?string
    {
        if (! $this->isEligible($photo, $variant)) {
            return null;
        }

        return $this->safeExistingPath($photo, $variant);
    }

    public function present(CommunityPhoto $photo, string $variant = 'thumbnail', bool $publicContextVerified = false): ?PublicCommunityPhotoPresentation
    {
        if (! $this->isEligible($photo, $variant, $publicContextVerified)) {
            return null;
        }

        $context = $photo->event !== null
            ? [$photo->event->title, route('gallery.events.show', $photo->event->slug)]
            : ($photo->specialAlbum !== null ? [$photo->specialAlbum->title, route('gallery.albums.show', $photo->specialAlbum->slug)] : [null, null]);

        $rotation = ((int) $photo->presentation_rotation % 360 + 360) % 360;
        $width = max(1, (int) ($photo->width ?? 960));
        $height = max(1, (int) ($photo->height ?? 640));
        if (in_array($rotation, [90, 270], true)) {
            [$width, $height] = [$height, $width];
        }

        return new PublicCommunityPhotoPresentation(
            id: $photo->id,
            imageUrl: route('gallery.photos.image', ['photo' => $photo->id, 'variant' => $variant]),
            detailUrl: route('gallery.photos.show', $photo->id),
            reportUrl: route('community-photos.reports.create', $photo->id),
            caption: $photo->caption,
            photographerName: $photo->photographer_name,
            contextLabel: $context[0],
            contextUrl: $context[1],
            width: $width,
            height: $height,
            rotationStyle: $rotation === 0 ? '' : 'transform: rotate('.$rotation.'deg)',
        );
    }

    private function safeExistingPath(CommunityPhoto $photo, string $variant): ?string
    {
        if (! in_array($variant, self::VARIANTS, true) || ! is_array($photo->processed_variants)) {
            return null;
        }
        foreach ($this->variantCandidates($variant) as $candidate) {
            $path = $photo->processed_variants[$candidate] ?? null;
            if (is_string($path) && PhotoStorageReference::isSafe((string) $photo->storage_disk, $path) && Storage::disk($photo->storage_disk)->exists($path)) {
                return $path;
            }
        }

        return null;
    }

    private function hasPublicContext(CommunityPhoto $photo): bool
    {
        if ($photo->special_album_id !== null) {
            return true;
        }

        return $photo->event_id !== null
            && $photo->event !== null
            && $this->events->isEligible($photo->event);
    }

    /** @return list<string> */
    private function variantCandidates(string $variant): array
    {
        return match ($variant) {
            'thumbnail' => ['thumbnail', 'medium', 'large', 'master'],
            'medium' => ['medium', 'large', 'master'],
            'large' => ['large', 'master'],
            'master' => ['master'],
            default => [],
        };
    }
}
