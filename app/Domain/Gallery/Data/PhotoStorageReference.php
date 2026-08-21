<?php

namespace App\Domain\Gallery\Data;

final readonly class PhotoStorageReference
{
    private function __construct(
        public string $disk,
        public string $path,
    ) {}

    public static function from(string $disk, string $path): self
    {
        if (! self::isSafe($disk, $path)) {
            throw new \InvalidArgumentException('Community photo storage references must use a generated gallery path on the configured disk.');
        }

        return new self($disk, $path);
    }

    public static function isSafe(string $disk, string $path): bool
    {
        if ($disk !== (string) config('gallery.photos.disk', 'local')) {
            return false;
        }

        $directory = trim((string) config('gallery.photos.directory', 'community-photos'), '/');

        return preg_match(
            '#\A'.preg_quote($directory, '#').'/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/[a-z0-9][a-z0-9_-]*\.(?:jpe?g|png|webp|avif)\z#Di',
            $path,
        ) === 1;
    }

    public static function isSafeDirectory(string $disk, string $path): bool
    {
        if ($disk !== (string) config('gallery.photos.disk', 'local')) {
            return false;
        }

        $directory = trim((string) config('gallery.photos.directory', 'community-photos'), '/');

        return preg_match('#\A'.preg_quote($directory, '#').'/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\z#Di', $path) === 1;
    }
}
