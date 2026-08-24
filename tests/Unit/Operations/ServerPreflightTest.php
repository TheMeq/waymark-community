<?php

namespace Tests\Unit\Operations;

use App\Domain\Operations\Installation\ServerEnvironment;
use App\Domain\Operations\Installation\ServerPreflight;
use PHPUnit\Framework\TestCase;

final class ServerPreflightTest extends TestCase
{
    public function test_supported_shared_host_reports_no_blocking_failures(): void
    {
        $report = (new ServerPreflight)->inspect($this->supportedEnvironment());

        $this->assertFalse($report->blocked());
        $this->assertSame('pass', $report->check('php')->status);
        $this->assertSame('pass', $report->check('extensions')->status);
        $this->assertSame('pass', $report->check('writable-directories')->status);
        $this->assertSame('pass', $report->check('image-library')->status);
    }

    public function test_incompatible_php_extensions_storage_and_image_support_have_plain_english_remediation(): void
    {
        $environment = $this->supportedEnvironment(
            phpVersion: '8.2.19',
            extensions: ['ctype', 'pdo'],
            writableDirectories: ['storage' => false, 'bootstrap/cache' => false],
            imageLibrary: null,
        );

        $report = (new ServerPreflight)->inspect($environment);

        $this->assertTrue($report->blocked());
        $this->assertStringContainsString('PHP 8.3 or newer', $report->check('php')->remediation);
        $this->assertStringContainsString('enable', strtolower($report->check('extensions')->remediation));
        $this->assertStringContainsString('zip', $report->check('extensions')->message);
        $this->assertStringContainsString('storage', $report->check('writable-directories')->message);
        $this->assertStringContainsString('GD or Imagick', $report->check('image-library')->remediation);
    }

    public function test_upload_https_debug_and_cron_limitations_are_reported_without_fake_guarantees(): void
    {
        $environment = $this->supportedEnvironment(
            uploadLimitBytes: 2 * 1024 * 1024,
            https: false,
            debug: true,
            cronAvailable: false,
        );

        $report = (new ServerPreflight)->inspect($environment);

        $this->assertSame('warning', $report->check('upload-limits')->status);
        $this->assertSame('warning', $report->check('https')->status);
        $this->assertSame('blocker', $report->check('debug')->status);
        $this->assertSame('warning', $report->check('cron')->status);
        $this->assertStringContainsString('cannot run on a reliable schedule', $report->check('cron')->message);
    }

    /** @param list<string>|null $extensions
     * @param  array<string, bool>|null  $writableDirectories
     */
    private function supportedEnvironment(
        string $phpVersion = '8.3.12',
        ?array $extensions = null,
        ?array $writableDirectories = null,
        int $uploadLimitBytes = 32 * 1024 * 1024,
        ?string $imageLibrary = 'GD',
        bool $https = true,
        bool $debug = false,
        bool $cronAvailable = true,
    ): ServerEnvironment {
        return new ServerEnvironment(
            phpVersion: $phpVersion,
            extensions: $extensions ?? ['ctype', 'curl', 'dom', 'exif', 'fileinfo', 'filter', 'gd', 'hash', 'mbstring', 'openssl', 'pdo', 'session', 'tokenizer', 'xml', 'zip'],
            writableDirectories: $writableDirectories ?? ['storage' => true, 'bootstrap/cache' => true],
            uploadLimitBytes: $uploadLimitBytes,
            postLimitBytes: $uploadLimitBytes,
            imageLibrary: $imageLibrary,
            https: $https,
            debug: $debug,
            cronAvailable: $cronAvailable,
        );
    }
}
