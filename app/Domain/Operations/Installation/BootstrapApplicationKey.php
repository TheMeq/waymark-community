<?php

namespace App\Domain\Operations\Installation;

final class BootstrapApplicationKey
{
    public static function resolve(?string $configuredKey, string $path): ?string
    {
        if (is_string($configuredKey) && trim($configuredKey) !== '') {
            return $configuredKey;
        }

        if (is_file($path)) {
            $stored = trim((string) file_get_contents($path));

            return str_starts_with($stored, 'base64:') ? $stored : null;
        }

        $directory = dirname($path);
        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            return null;
        }

        $key = 'base64:'.base64_encode(random_bytes(32));
        $temporaryPath = $path.'.tmp';

        if (@file_put_contents($temporaryPath, $key."\n", LOCK_EX) === false) {
            return null;
        }

        @chmod($temporaryPath, 0600);

        if (! @rename($temporaryPath, $path)) {
            @unlink($temporaryPath);

            return null;
        }

        return $key;
    }
}
