<?php

namespace App\Domain\Walks\Data;

use App\Domain\Walks\Models\Walk;
use Illuminate\Support\Facades\Storage;

final readonly class WalkAttachment
{
    private function __construct(
        public string $path,
        public string $name,
    ) {}

    /** @return array<int, array{index: int, name: string}> */
    public static function availableForWalk(Walk $walk): array
    {
        if (! is_array($walk->attachments)) {
            return [];
        }

        $attachments = [];

        foreach ($walk->attachments as $index => $attachment) {
            if (! is_int($index) || ($validated = self::available($attachment)) === null) {
                continue;
            }

            $attachments[] = ['index' => $index, 'name' => $validated->name];
        }

        return $attachments;
    }

    public static function at(Walk $walk, int $index): ?self
    {
        $attachments = $walk->attachments;

        return self::available(is_array($attachments) ? ($attachments[$index] ?? null) : null);
    }

    private static function available(mixed $attachment): ?self
    {
        if (! is_array($attachment)
            || ! is_string($attachment['path'] ?? null)
            || ! is_string($attachment['name'] ?? null)
            || ! self::isSafePath($attachment['path'])
            || ! Storage::disk(self::disk())->exists($attachment['path'])) {
            return null;
        }

        $name = self::safeName($attachment['name']);

        return $name === null ? null : new self($attachment['path'], $name);
    }

    private static function isSafePath(string $path): bool
    {
        $directory = trim((string) config('walks.attachments.directory', 'walks/attachments'), '/');
        $prefix = $directory.'/';

        if (! str_starts_with($path, $prefix) || str_contains($path, "\0")) {
            return false;
        }

        $relative = substr($path, strlen($prefix));

        return $relative !== ''
            && preg_match('#^[A-Za-z0-9][A-Za-z0-9._/-]*$#D', $relative) === 1
            && ! str_contains($relative, '..');
    }

    private static function safeName(string $name): ?string
    {
        $name = trim($name);

        if (! self::isResponseSafeName($name)) {
            return null;
        }

        return $name;
    }

    public static function isResponseSafeName(string $name): bool
    {
        $name = trim($name);

        return $name !== ''
            && strlen($name) <= 255
            && ! str_contains($name, '/')
            && ! str_contains($name, '\\')
            && preg_match('/[\x00-\x1F\x7F]/', $name) !== 1;
    }

    private static function disk(): string
    {
        return (string) config('walks.attachments.disk', 'local');
    }
}
