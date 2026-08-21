<?php

namespace App\Domain\Holidays\Data;

use App\Domain\Holidays\Models\Holiday;
use Illuminate\Support\Facades\Storage;

final readonly class HolidayAttachment
{
    private function __construct(public string $path, public string $name) {}

    /** @return array<int, array{index: int, name: string}> */
    public static function availableFor(Holiday $holiday): array
    {
        $available = [];
        foreach ($holiday->attachments ?? [] as $index => $attachment) {
            if (is_int($index) && ($item = self::available($attachment)) !== null) {
                $available[] = ['index' => $index, 'name' => $item->name];
            }
        }

        return $available;
    }

    public static function at(Holiday $holiday, int $index): ?self
    {
        return self::available(($holiday->attachments ?? [])[$index] ?? null);
    }

    private static function available(mixed $attachment): ?self
    {
        if (! is_array($attachment)
            || ! is_string($attachment['path'] ?? null)
            || ! is_string($attachment['name'] ?? null)
            || preg_match('#\Aholidays/attachments/[A-Za-z0-9][A-Za-z0-9._-]*\z#D', $attachment['path']) !== 1
            || ! Storage::disk(self::disk())->exists($attachment['path'])) {
            return null;
        }
        $name = trim($attachment['name']);
        if ($name === '' || strlen($name) > 255 || preg_match('~[\x00-\x1F\x7F\\/]~', $name) === 1) {
            return null;
        }

        return new self($attachment['path'], $name);
    }

    private static function disk(): string
    {
        return (string) config('holidays.attachments.disk', 'local');
    }
}
