<?php

namespace App\Domain\Operations\Updates;

use App\Domain\Operations\Updates\Contracts\ReleasePackageDownloader;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class NativeReleasePackageDownloader implements ReleasePackageDownloader
{
    public function download(string $url, string $destination): void
    {
        if (! str_starts_with(strtolower($url), 'https://')) {
            throw new RuntimeException('The release package URL is not secure.');
        }
        $response = Http::timeout(120)
            ->withOptions(['allow_redirects' => false, 'sink' => $destination])
            ->get($url);
        if (! $response->successful() || ! is_file($destination)) {
            @unlink($destination);
            throw new RuntimeException('The verified release package could not be downloaded.');
        }
    }
}
