<?php

namespace App\Domain\Operations\Installation;

use Illuminate\Http\Request;

final class NativeServerEnvironment
{
    public function capture(Request $request): ServerEnvironment
    {
        $extensions = array_map('strtolower', get_loaded_extensions());

        return new ServerEnvironment(
            phpVersion: PHP_VERSION,
            extensions: $extensions,
            writableDirectories: [
                'storage' => is_writable(storage_path()),
                'bootstrap/cache' => is_writable(base_path('bootstrap/cache')),
            ],
            uploadLimitBytes: $this->bytes((string) ini_get('upload_max_filesize')),
            postLimitBytes: $this->bytes((string) ini_get('post_max_size')),
            imageLibrary: extension_loaded('imagick') ? 'Imagick' : (extension_loaded('gd') ? 'GD' : null),
            https: $request->secure(),
            debug: (bool) config('app.debug'),
            cronAvailable: filter_var(env('WAYMARK_CRON_AVAILABLE', app()->environment('testing')), FILTER_VALIDATE_BOOL),
        );
    }

    private function bytes(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        $number = (float) $value;

        return (int) ($number * match (strtolower(substr($value, -1))) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        });
    }
}
