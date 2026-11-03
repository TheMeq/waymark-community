<?php

namespace App\Domain\Operations\Updates;

use App\Domain\Operations\Updates\Contracts\ReleasePackageDownloader;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Throwable;

final class NativeReleasePackageDownloader implements ReleasePackageDownloader
{
    public function download(string $url, string $destination): void
    {
        try {
            $uri = new Uri($url);
        } catch (Throwable) {
            throw new RuntimeException('The release package URL is not secure.');
        }
        if ($uri->getScheme() !== 'https' || $uri->getHost() === '') {
            throw new RuntimeException('The release package URL is not secure.');
        }

        try {
            $response = Http::timeout(120)
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 5,
                        'protocols' => ['https'],
                        'strict' => true,
                        'referer' => false,
                        'on_redirect' => static function (RequestInterface $request, ResponseInterface $response, UriInterface $redirect): void {
                            if ($redirect->getScheme() !== 'https' || $redirect->getHost() === '') {
                                throw new RuntimeException('The release package redirect is not secure.');
                            }
                        },
                    ],
                    'sink' => $destination,
                ])
                ->get($url);
        } catch (Throwable $exception) {
            @unlink($destination);

            throw new RuntimeException('The verified release package could not be downloaded.', previous: $exception);
        }

        if (! $response->successful() || ! is_file($destination)) {
            @unlink($destination);
            throw new RuntimeException('The verified release package could not be downloaded.');
        }
    }
}
