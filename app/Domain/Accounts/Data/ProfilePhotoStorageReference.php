<?php

namespace App\Domain\Accounts\Data;

final class ProfilePhotoStorageReference
{
    public static function isSafe(string $disk, string $path): bool
    {
        return $disk === (string) config('accounts.profile_photos.disk', 'local')
            && preg_match('#\Aprofile-photos/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/(?:medium|thumbnail)\.(?:jpe?g|png|webp|avif)\z#Di', $path) === 1;
    }

    public static function isSafeDirectory(string $disk, string $path): bool
    {
        return $disk === (string) config('accounts.profile_photos.disk', 'local')
            && preg_match('#\Aprofile-photos/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\z#Di', $path) === 1;
    }
}
