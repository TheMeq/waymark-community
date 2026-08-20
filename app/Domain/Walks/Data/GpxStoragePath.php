<?php

namespace App\Domain\Walks\Data;

use Illuminate\Support\Str;

final class GpxStoragePath
{
    public static function generate(): string
    {
        return self::directory().'/'.Str::uuid()->toString().'.gpx';
    }

    public static function isGenerated(?string $path): bool
    {
        if (! is_string($path)) {
            return false;
        }

        return preg_match(
            '#^'.preg_quote(self::directory(), '#').'/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\\.gpx$#D',
            $path,
        ) === 1;
    }

    private static function directory(): string
    {
        return trim((string) config('walks.gpx.directory', 'walks/gpx'), '/');
    }
}
