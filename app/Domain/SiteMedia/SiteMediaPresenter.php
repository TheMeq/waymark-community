<?php

namespace App\Domain\SiteMedia;

use App\Domain\SiteMedia\Data\SiteMediaPresentation;
use App\Domain\SiteMedia\Data\SiteMediaStorageReference;
use App\Domain\SiteMedia\Models\SiteMedia;
use Illuminate\Support\Facades\Storage;

final class SiteMediaPresenter
{
    public function present(SiteMedia $media, string $variant = 'master'): ?SiteMediaPresentation
    {
        $path = is_array($media->processed_variants) ? ($media->processed_variants[$variant] ?? null) : null;
        if ($media->processing_status !== 'complete' || ! is_string($path) || ! SiteMediaStorageReference::isSafe($media->storage_disk, $path) || ! Storage::disk($media->storage_disk)->exists($path)) {
            return null;
        }

        return new SiteMediaPresentation($media->id, route('site-media.stream', [$media, $variant]), $media->is_decorative ? '' : (string) $media->alt_text, max(1, $media->width), max(1, $media->height), (float) $media->focal_point_x, (float) $media->focal_point_y);
    }
}
