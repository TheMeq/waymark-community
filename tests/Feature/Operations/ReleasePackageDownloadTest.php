<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Updates\NativeReleasePackageDownloader;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ReleasePackageDownloadTest extends TestCase
{
    public function test_https_release_is_downloaded_to_private_staging_without_redirects(): void
    {
        Http::fake(['https://updates.example.test/release.zip' => Http::response('package-bytes', 200)]);
        $destination = storage_path('framework/testing/release-download-'.bin2hex(random_bytes(8)).'.zip');

        try {
            (new NativeReleasePackageDownloader)->download('https://updates.example.test/release.zip', $destination);
            $this->assertSame('package-bytes', file_get_contents($destination));
            Http::assertSent(fn ($request): bool => $request->url() === 'https://updates.example.test/release.zip');
        } finally {
            @unlink($destination);
        }
    }

    public function test_non_https_release_url_is_refused(): void
    {
        $this->expectException(\RuntimeException::class);
        (new NativeReleasePackageDownloader)->download('http://updates.example.test/release.zip', storage_path('framework/testing/not-created.zip'));
    }
}
