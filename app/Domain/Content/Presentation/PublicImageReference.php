<?php

namespace App\Domain\Content\Presentation;

final class PublicImageReference
{
    private const MAX_LENGTH = 2048;

    public static function isAllowed(mixed $reference): bool
    {
        if (! is_string($reference)
            || $reference === ''
            || mb_strlen($reference) > self::MAX_LENGTH
            || trim($reference) !== $reference
            || preg_match('/[\x00-\x20\x7f]/u', $reference) === 1
            || preg_match('/%(?![0-9a-f]{2})/i', $reference) === 1) {
            return false;
        }

        $decoded = self::fullyDecode($reference);

        if ($decoded === null
            || preg_match('/[\x00-\x20\x7f]/u', $decoded) === 1
            || str_contains($decoded, '\\')
            || self::containsTraversal($decoded)
            || preg_match('#\A/?[a-z]:/#i', $decoded) === 1) {
            return false;
        }

        if (str_starts_with($decoded, '/')) {
            return self::isSafeSitePath($decoded);
        }

        $parts = parse_url($reference);

        return is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && filled($parts['host'] ?? null)
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && filter_var($reference, FILTER_VALIDATE_URL) !== false;
    }

    public static function resolve(mixed $reference): ?string
    {
        if (! self::isAllowed($reference)) {
            return null;
        }

        return str_starts_with($reference, '/') ? PublicUrl::asset($reference) : $reference;
    }

    private static function fullyDecode(string $reference): ?string
    {
        $decoded = $reference;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $next = rawurldecode($decoded);

            if ($next === $decoded) {
                return $decoded;
            }

            $decoded = $next;
        }

        return rawurldecode($decoded) === $decoded ? $decoded : null;
    }

    private static function containsTraversal(string $reference): bool
    {
        $path = parse_url($reference, PHP_URL_PATH);

        if (! is_string($path)) {
            return true;
        }

        return collect(explode('/', $path))->contains(
            static fn (string $segment): bool => $segment === '.' || $segment === '..',
        );
    }

    private static function isSafeSitePath(string $reference): bool
    {
        if (str_starts_with($reference, '//')) {
            return false;
        }

        $path = parse_url($reference, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return false;
        }

        return preg_match('#\A/(?:application|storage)(?:/|\z)#i', $path) !== 1
            && preg_match('#\A/(?:etc|home|opt|private|root|srv|tmp|usr|var|volumes|windows)(?:/|\z)#i', $path) !== 1;
    }
}
