<?php

namespace Tests\Integration\Operations;

use App\Domain\Operations\Updates\NativeReleasePackageDownloader;
use Tests\TestCase;

final class ReleasePackageDownloadNetworkTest extends TestCase
{
    public function test_production_downloader_fetches_the_expected_immutable_release_asset(): void
    {
        $url = getenv('WAYMARK_RELEASE_NETWORK_URL');
        $sha256 = getenv('WAYMARK_RELEASE_NETWORK_SHA256');
        if (! is_string($url) || ! is_string($sha256) || preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1) {
            $this->markTestSkipped('Set WAYMARK_RELEASE_NETWORK_URL and WAYMARK_RELEASE_NETWORK_SHA256 to run the release network smoke.');
        }

        $destination = storage_path('framework/testing/network-release-download-'.bin2hex(random_bytes(8)).'.zip');

        try {
            (new NativeReleasePackageDownloader)->download($url, $destination);

            $this->assertTrue(hash_equals($sha256, hash_file('sha256', $destination)));
        } finally {
            @unlink($destination);
        }
    }
}
