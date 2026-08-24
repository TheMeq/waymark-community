<?php

namespace Tests\Unit\Operations;

use App\Domain\Operations\Updates\ReleaseMetadata;
use App\Domain\Operations\Updates\UpdateCompatibilityChecker;
use App\Domain\Operations\Updates\UpdateEnvironment;
use PHPUnit\Framework\TestCase;

final class UpdateCompatibilityTest extends TestCase
{
    public function test_supported_host_passes_php_extension_database_and_disk_checks(): void
    {
        $report = (new UpdateCompatibilityChecker)->check($this->metadata(), new UpdateEnvironment(
            '8.3.12', ['ctype', 'openssl', 'zip'], 'mysql', '8.4.2', 100000000,
        ));

        $this->assertTrue($report->compatible());
        $this->assertSame(['pass', 'pass', 'pass', 'pass'], array_column($report->checks, 'status'));
    }

    public function test_unsupported_host_reports_each_blocker_in_plain_language(): void
    {
        $report = (new UpdateCompatibilityChecker)->check($this->metadata(), new UpdateEnvironment(
            '8.2.20', ['ctype'], 'mariadb', '10.5.4', 1000,
        ));

        $this->assertFalse($report->compatible());
        $this->assertStringContainsString('PHP 8.3.0', $report->checks[0]['message']);
        $this->assertStringContainsString('openssl', $report->checks[1]['message']);
        $this->assertStringContainsString('MariaDB 10.6.0', $report->checks[2]['message']);
        $this->assertStringContainsString('disk', strtolower($report->checks[3]['message']));
    }

    private function metadata(): ReleaseMetadata
    {
        return new ReleaseMetadata(
            version: '1.2.0', publishedAt: '2026-08-24T09:00:00Z', securityRelease: false,
            summary: 'A release.', releaseNotes: [], packageUrl: 'https://updates.example.test/release.zip',
            packageSha256: str_repeat('a', 64), packageSizeBytes: 10000000,
            requirements: ['php' => '8.3.0', 'extensions' => ['ctype', 'openssl', 'zip'], 'database' => ['mysql' => '8.0.0', 'mariadb' => '10.6.0'], 'disk_free_bytes' => 50000000],
        );
    }
}
