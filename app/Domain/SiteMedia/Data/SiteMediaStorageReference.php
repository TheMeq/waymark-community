<?php

namespace App\Domain\SiteMedia\Data;

final readonly class SiteMediaStorageReference
{
    public static function isSafe(string $disk, string $path): bool
    {
        return $disk === (string) config('gallery.photos.disk', 'local')
            && preg_match('#\Asite-media/[a-f0-9-]{36}/[a-z0-9][a-z0-9_-]*\.(?:jpe?g|png|webp|avif)\z#Di', $path) === 1;
    }
}
