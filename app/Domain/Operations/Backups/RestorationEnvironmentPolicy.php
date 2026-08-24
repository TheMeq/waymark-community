<?php

namespace App\Domain\Operations\Backups;

use Dotenv\Dotenv;
use RuntimeException;

final class RestorationEnvironmentPolicy
{
    /** @var list<string> */
    private const array PORTABLE_KEYS = [
        'APP_KEY',
        'APP_PREVIOUS_KEYS',
    ];

    public function export(string $sourcePath, string $destinationPath): void
    {
        $portable = $this->portableValues($sourcePath);
        if (! is_string($portable['APP_KEY'] ?? null) || trim($portable['APP_KEY']) === '') {
            throw new RuntimeException('The application cryptographic identity is unavailable for backup.');
        }

        $this->write($destinationPath, $this->encodeValues($portable));
    }

    public function merge(string $sourcePath, string $targetPath): void
    {
        if (! is_file($targetPath)) {
            throw new RuntimeException('The target host environment is unavailable for a safe restore.');
        }

        $portable = $this->portableValues($sourcePath);
        if (! is_string($portable['APP_KEY'] ?? null) || trim($portable['APP_KEY']) === '') {
            throw new RuntimeException('The backup does not contain a valid application cryptographic identity.');
        }

        $lines = preg_split('/\R/', (string) file_get_contents($targetPath)) ?: [];
        $written = [];
        foreach ($lines as &$line) {
            if (preg_match('/^([A-Z][A-Z0-9_]*)=/', $line, $matches) !== 1 || ! array_key_exists($matches[1], $portable)) {
                continue;
            }
            $key = $matches[1];
            $line = $key.'='.$this->encode((string) $portable[$key]);
            $written[$key] = true;
        }
        unset($line);

        foreach ($portable as $key => $value) {
            if (! isset($written[$key])) {
                $lines[] = $key.'='.$this->encode((string) $value);
            }
        }

        $this->write($targetPath, rtrim(implode("\n", $lines))."\n");
    }

    /** @return array<string, string|null> */
    private function portableValues(string $sourcePath): array
    {
        if (! is_file($sourcePath)) {
            throw new RuntimeException('Restoration configuration is unavailable.');
        }

        try {
            $parsed = Dotenv::parse((string) file_get_contents($sourcePath));
        } catch (\Throwable $exception) {
            throw new RuntimeException('Restoration configuration is invalid.', previous: $exception);
        }

        return array_intersect_key($parsed, array_fill_keys(self::PORTABLE_KEYS, true));
    }

    /** @param array<string, string|null> $values */
    private function encodeValues(array $values): string
    {
        $lines = [];
        foreach (self::PORTABLE_KEYS as $key) {
            if (array_key_exists($key, $values) && $values[$key] !== null) {
                $lines[] = $key.'='.$this->encode((string) $values[$key]);
            }
        }

        return implode("\n", $lines)."\n";
    }

    private function encode(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (preg_match('/^[A-Za-z0-9_\.\/:+@=-]+$/', $value) === 1) {
            return $value;
        }

        return '"'.str_replace(
            ['\\', '"', '$', "\r", "\n"],
            ['\\\\', '\\"', '\\$', '', '\\n'],
            $value,
        ).'"';
    }

    private function write(string $path, string $contents): void
    {
        $temporary = $path.'.tmp-'.bin2hex(random_bytes(6));
        if (file_put_contents($temporary, $contents, LOCK_EX) === false || ! rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Restoration configuration could not be written safely.');
        }
        @chmod($path, 0600);
    }
}
