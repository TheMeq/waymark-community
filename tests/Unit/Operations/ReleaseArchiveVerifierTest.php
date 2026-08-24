<?php

namespace Tests\Unit\Operations;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Waymark\Release\ReleaseArchiveBuilder;
use Waymark\Release\ReleaseArchiveVerifier;
use ZipArchive;

require_once dirname(__DIR__, 3).'/scripts/release/ReleaseArchiveBuilder.php';
require_once dirname(__DIR__, 3).'/scripts/release/ReleaseArchiveVerifier.php';

final class ReleaseArchiveVerifierTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/waymark-release-verifier-'.bin2hex(random_bytes(8));
        foreach ([
            'bootstrap/cache/.gitignore',
            'bootstrap/app.php',
            'database/migrations/2026_08_24_130000_release.php',
            'public/build/manifest.json',
            'public/index.php',
            'storage/app/private/.gitignore',
            'storage/framework/cache/.gitignore',
            'vendor/autoload.php',
            'vendor/composer/installed.json',
        ] as $path) {
            $contents = match ($path) {
                'vendor/composer/installed.json' => '{"packages":[]}',
                'public/build/manifest.json' => '{}',
                default => $path,
            };
            $this->write($path, $contents);
        }
        foreach (['VERSION' => "1.0.0\n", 'artisan' => 'artisan', '.env.example' => 'APP_KEY='] as $path => $contents) {
            $this->write($path, $contents);
        }
    }

    protected function tearDown(): void
    {
        $this->remove($this->directory);
        parent::tearDown();
    }

    public function test_verifier_accepts_a_manifest_complete_production_archive_and_reports_exact_evidence(): void
    {
        $archive = $this->package();

        $result = (new ReleaseArchiveVerifier)->verify($archive);

        $this->assertSame('1.0.0', $result['version']);
        $this->assertSame(str_repeat('b', 40), $result['commit']);
        $this->assertSame('8.3.0', $result['minimum_php']);
        $this->assertSame('2026_08_24_130000', $result['latest_migration']);
        $this->assertSame(12, $result['file_count']);
        $this->assertSame(hash_file('sha256', $archive), $result['sha256']);
    }

    public function test_verifier_rejects_an_undeclared_secret_entry(): void
    {
        $archivePath = $this->package();
        $archive = new ZipArchive;
        $this->assertTrue($archive->open($archivePath));
        $archive->addFromString('application/.env', 'APP_KEY=secret');
        $archive->close();
        file_put_contents($archivePath.'.sha256', hash_file('sha256', $archivePath).'  '.basename($archivePath)."\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('verification failed');
        (new ReleaseArchiveVerifier)->verify($archivePath);
    }

    public function test_verifier_rejects_a_package_without_production_composer_dependencies(): void
    {
        unlink($this->directory.'/application/vendor/autoload.php');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('verification failed');
        (new ReleaseArchiveVerifier)->verify($this->package());
    }

    public function test_verifier_rejects_development_dependencies_even_when_manifest_declared(): void
    {
        $this->write('vendor/phpunit/phpunit/phpunit', 'developer dependency');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('verification failed');
        (new ReleaseArchiveVerifier)->verify($this->package());
    }

    public function test_verifier_requires_the_adjacent_archive_checksum_to_match(): void
    {
        $archive = $this->package();
        file_put_contents($archive.'.sha256', str_repeat('0', 64).'  '.basename($archive)."\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('verification failed');
        (new ReleaseArchiveVerifier)->verify($archive);
    }

    private function package(): string
    {
        $path = $this->directory.'/release.zip';
        (new ReleaseArchiveBuilder)->build($this->directory.'/application', $path, [
            'version' => '1.0.0',
            'commit' => str_repeat('b', 40),
            'built_at' => '2026-08-24T12:00:00Z',
            'minimum_php' => '8.3.0',
            'schema' => '2026_08_24_130000',
        ]);

        return $path;
    }

    private function write(string $path, string $contents): void
    {
        $target = $this->directory.'/application/'.$path;
        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0700, true);
        }
        file_put_contents($target, $contents);
    }

    private function remove(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }
}
