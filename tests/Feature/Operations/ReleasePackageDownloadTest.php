<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Updates\NativeReleasePackageDownloader;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ReleasePackageDownloadTest extends TestCase
{
    public function test_direct_https_release_is_downloaded_to_private_staging(): void
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

    public function test_one_https_redirect_is_followed_to_the_release_package(): void
    {
        Http::fake([
            'https://updates.example.test/release.zip' => Http::response('', 302, ['Location' => 'https://assets.example.test/release.zip']),
            'https://assets.example.test/release.zip' => Http::response('redirected-package-bytes', 200),
        ]);
        $destination = storage_path('framework/testing/redirected-release-download-'.bin2hex(random_bytes(8)).'.zip');

        try {
            (new NativeReleasePackageDownloader)->download('https://updates.example.test/release.zip', $destination);
            $this->assertSame('redirected-package-bytes', file_get_contents($destination));
            Http::assertSentCount(2);
        } finally {
            @unlink($destination);
        }
    }

    public function test_multiple_allowed_https_redirects_are_followed(): void
    {
        Http::fake([
            'https://updates.example.test/release.zip' => Http::response('', 302, ['Location' => 'https://releases.example.test/release.zip']),
            'https://releases.example.test/release.zip' => Http::response('', 307, ['Location' => 'https://assets.example.test/release.zip']),
            'https://assets.example.test/release.zip' => Http::response('multi-hop-package-bytes', 200),
        ]);
        $destination = storage_path('framework/testing/multi-redirect-release-download-'.bin2hex(random_bytes(8)).'.zip');

        try {
            (new NativeReleasePackageDownloader)->download('https://updates.example.test/release.zip', $destination);
            $this->assertSame('multi-hop-package-bytes', file_get_contents($destination));
            Http::assertSentCount(3);
        } finally {
            @unlink($destination);
        }
    }

    public function test_non_https_release_url_is_refused(): void
    {
        $this->expectException(\RuntimeException::class);
        (new NativeReleasePackageDownloader)->download('http://updates.example.test/release.zip', storage_path('framework/testing/not-created.zip'));
    }

    public function test_https_to_http_redirect_is_refused_and_partial_download_is_removed(): void
    {
        Http::fake([
            'https://updates.example.test/release.zip' => Http::response('redirect-body', 302, ['Location' => 'http://assets.example.test/release.zip']),
        ]);
        $destination = storage_path('framework/testing/downgrade-release-download-'.bin2hex(random_bytes(8)).'.zip');

        try {
            (new NativeReleasePackageDownloader)->download('https://updates.example.test/release.zip', $destination);
            $this->fail('An HTTPS to HTTP redirect was unexpectedly followed.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('could not be downloaded', $exception->getMessage());
        }

        $this->assertFileDoesNotExist($destination);
        Http::assertNotSent(fn ($request): bool => str_starts_with($request->url(), 'http://'));
    }

    public function test_more_than_five_redirects_are_refused_and_partial_download_is_removed(): void
    {
        $attempts = 0;
        Http::fake(function ($request) use (&$attempts) {
            $attempts++;

            return Http::response('redirect-body', 302, ['Location' => $request->url()]);
        });
        $destination = storage_path('framework/testing/excessive-redirect-release-download-'.bin2hex(random_bytes(8)).'.zip');

        try {
            (new NativeReleasePackageDownloader)->download('https://updates.example.test/release.zip', $destination);
            $this->fail('An excessive redirect chain was unexpectedly followed.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('could not be downloaded', $exception->getMessage());
        }

        $this->assertFileDoesNotExist($destination);
        $this->assertSame(6, $attempts);
    }

    public function test_malformed_redirect_location_is_refused_and_partial_download_is_removed(): void
    {
        Http::fake([
            'https://updates.example.test/release.zip' => Http::response('redirect-body', 302, ['Location' => 'https://[invalid']),
        ]);
        $destination = storage_path('framework/testing/malformed-redirect-release-download-'.bin2hex(random_bytes(8)).'.zip');

        try {
            (new NativeReleasePackageDownloader)->download('https://updates.example.test/release.zip', $destination);
            $this->fail('A malformed redirect location was unexpectedly followed.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('could not be downloaded', $exception->getMessage());
        }

        $this->assertFileDoesNotExist($destination);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://updates.example.test/release.zip');
    }

    public function test_cross_host_redirect_does_not_forward_authorization_or_cookie_headers(): void
    {
        Http::globalOptions([
            'headers' => [
                'Authorization' => 'Bearer private-release-token',
                'Cookie' => 'waymark_private_session=secret',
            ],
        ]);
        Http::fake([
            'https://updates.example.test/release.zip' => Http::response('', 302, ['Location' => 'https://assets.example.test/release.zip']),
            'https://assets.example.test/release.zip' => Http::response('package-bytes', 200),
        ]);
        $destination = storage_path('framework/testing/cross-host-release-download-'.bin2hex(random_bytes(8)).'.zip');

        try {
            (new NativeReleasePackageDownloader)->download('https://updates.example.test/release.zip', $destination);
            $requests = Http::recorded();
            $this->assertCount(2, $requests);
            $this->assertSame(['Bearer private-release-token'], $requests[0][0]->header('Authorization'));
            $this->assertSame(['waymark_private_session=secret'], $requests[0][0]->header('Cookie'));
            $this->assertSame([], $requests[1][0]->header('Authorization'));
            $this->assertSame([], $requests[1][0]->header('Cookie'));
        } finally {
            @unlink($destination);
        }
    }

    public function test_failed_download_does_not_leave_a_partial_staged_package(): void
    {
        Http::fake(['https://updates.example.test/release.zip' => Http::response('partial-error-body', 503)]);
        $destination = storage_path('framework/testing/failed-release-download-'.bin2hex(random_bytes(8)).'.zip');

        try {
            (new NativeReleasePackageDownloader)->download('https://updates.example.test/release.zip', $destination);
            $this->fail('A failed release response was unexpectedly accepted.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('could not be downloaded', $exception->getMessage());
        }

        $this->assertFileDoesNotExist($destination);
    }
}
