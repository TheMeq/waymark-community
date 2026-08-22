<?php

namespace App\Domain\Gallery;

use App\Domain\Gallery\Data\PhotoStorageReference;
use App\Domain\Gallery\Data\PublicCommunityPhotoPresentation;
use App\Domain\Gallery\Models\CommunityPhoto;
use Illuminate\Support\Facades\Storage;

final class PublicCommunityPhotoPresenter
{
    /** @var list<string> */
    private const VARIANTS = ['thumbnail', 'medium', 'large', 'master'];

    public function isEligible(CommunityPhoto $photo, string $variant = 'thumbnail'): bool
    {
        return $photo->moderation_status === 'approved'
            && $photo->published_at !== null
            && $photo->processing_status === 'complete'
            && $this->safeExistingPath($photo, $variant) !== null;
    }

    public function pathFor(CommunityPhoto $photo, string $variant): ?string
    {
        if (! $this->isEligible($photo, $variant)) {
            return null;
        }

        return $this->safeExistingPath($photo, $variant);
    }

    public function present(CommunityPhoto $photo, string $variant = 'thumbnail'): ?PublicCommunityPhotoPresentation
    {
        if (! $this->isEligible($photo, $variant)) {
            return null;
        }

        $context = $photo->event !== null
            ? [$photo->event->title, route('gallery.events.show', $photo->event->slug)]
            : ($photo->specialAlbum !== null ? [$photo->specialAlbum->title, route('gallery.albums.show', $photo->specialAlbum->slug)] : [null, null]);

        return new PublicCommunityPhotoPresentation(
            id: $photo->id,
            imageUrl: route('gallery.photos.image', ['photo' => $photo->id, 'variant' => $variant]),
            detailUrl: route('gallery.photos.show', $photo->id),
            reportUrl: route('community-photos.reports.create', $photo->id),
            caption: $photo->caption,
            photographerName: $photo->photographer_name,
            contextLabel: $context[0],
            contextUrl: $context[1],
            width: max(1, (int) ($photo->width ?? 960)),
            height: max(1, (int) ($photo->height ?? 640)),
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
