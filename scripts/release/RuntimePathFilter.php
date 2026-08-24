<?php

declare(strict_types=1);

namespace Waymark\Release;

final class RuntimePathFilter
{
    public static function excludes(string $path): bool
    {
        $normalized = strtolower(str_replace('\\', '/', $path));

        return str_starts_with($normalized, 'storage/')
            && ! str_ends_with($normalized, '/.gitignore');
    }
}
