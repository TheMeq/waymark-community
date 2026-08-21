<?php

namespace App\Domain\Socials\Data;

use App\Domain\Socials\Models\Social;
use Illuminate\Support\Facades\Storage;

final readonly class SocialAttachment
{
    private function __construct(public string $path, public string $name) {}

    /** @return array<int, array{index: int, name: string}> */
    public static function availableFor(Social $social): array
    {
        $available = [];

        foreach ($social->attachments ?? [] as $index => $attachment) {
            if (is_int($index) && ($item = self::available($attachment)) !== null) {
                $available[] = ['index' => $index, 'name' => $item->name];
            }
        }

        return $available;
    }

    public static function at(Social $social, int $index): ?self
    {
        return self::available(($social->attachments ?? [])[$index] ?? null);
    }

    private static function available(mixed $attachment): ?self
    {
        if (! is_array($attachment)
            || ! is_string($attachment['path'] ?? null)
            || ! is_string($attachment['name'] ?? null)
            || ! str_starts_with($attachment['path'], 'socials/attachments/')
            || str_contains($attachment['path'], '..')
            || str_contains($attachment['path'], "\0")
            || preg_match('#^[A-Za-z0-9][A-Za-z0-9._/-]*$#D', $attachment['path']) !== 1
            || ! Storage::disk(self::disk())->exists($attachment['path'])) {
            return null;
        }

        $name = trim($attachment['name']);

        if ($name === '' || strlen($name) > 255 || str_contains($name, '/') || str_contains($name, '\\') || preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
            return null;
        }

        return new self($attachment['path'], $name);
    }

    private static function disk(): string
    {
        return (string) config('socials.attachments.disk', 'local');
    }
}
