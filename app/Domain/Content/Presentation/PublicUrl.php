<?php

namespace App\Domain\Content\Presentation;

use App\Domain\Content\Models\NavigationItem;

final class PublicUrl
{
    public static function resolve(mixed $url): ?string
    {
        if (! is_string($url) || ! NavigationItem::isAllowedUrl($url)) {
            return null;
        }

        return str_starts_with($url, '/') ? self::withApplicationPrefix($url) : $url;
    }

    public static function asset(mixed $reference): ?string
    {
        if (! is_string($reference) || ! NavigationItem::isAllowedUrl($reference)) {
            return null;
        }

        return str_starts_with($reference, '/') ? self::withApplicationPrefix($reference) : $reference;
    }

    private static function withApplicationPrefix(string $path): string
    {
        $basePath = rtrim((string) parse_url(route('home', absolute: false), PHP_URL_PATH), '/');

        return $basePath.$path;
    }
}
