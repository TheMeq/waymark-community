<?php

namespace Tests\Unit\Operations;

use App\Domain\Operations\Updates\ReleaseMetadata;
use App\Domain\Operations\Updates\ReleasePackageVerifier;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

final class ReleasePackageVerifierTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'waymark-release-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $this->clean($this->directory);
        parent::tearDown();
    }

    public function test_signed_metadata_checksum_and_internal_manifest_produce_safe_staged_release(): void
    {
        $package = $this->package(['VERSION' => "1.2.0\n", 'artisan' => 'release-artisan', 'bootstrap/app.php' => 'release-bootstrap', 'public/index.php' => 'release-front', 'vendor/autoload.php' => 'release-autoload', 'public/build/manifest.json' => '{}', 'app-marker.txt' => 'new']);
        $metadata = $this->metadata($package);

        $verified = (new ReleasePackageVerifier)->stage($package, $metadata, $this->directory.DIRECTORY_SEPARATOR.'stage');

        $this->assertSame('new', file_get_contents($verified->applicationPath('app-marker.txt')));
        $this->assertContains('obsolete.txt', $verified->deletes);
        $verified->cleanup();
    }

    public function test_wrong_package_checksum_is_rejected_before_extraction(): void
    {
        $package = $this->package(['VERSION' => "1.2.0\n", 'artisan' => 'x', 'bootstrap/app.php' => 'x', 'public/index.php' => 'x', 'vendor/autoload.php' => 'x', 'public/build/manifest.json' => '{}']);
        $metadata = $this->metadata($package, str_repeat('0', 64));

        $this->expectException(RuntimeException::class);
        (new ReleasePackageVerifier)->stage($package, $metadata, $this->directory.DIRECTORY_SEPARATOR.'stage');
    }

    public function test_traversal_or_private_runtime_paths_are_rejected(): void
    {
        $package = $this->package(['VERSION' => "1.2.0\n", 'artisan' => 'x', 'bootstrap/app.php' => 'x', 'public/index.php' => 'x', 'vendor/autoload.php' => 'x', 'public/build/manifest.json' => '{}', '../outside.php' => 'unsafe']);

        $this->expectException(RuntimeException::class);
        (new ReleasePackageVerifier)->stage($package, $this->metadata($package), $this->directory.DIRECTORY_SEPARATOR.'stage');
    }

    public function test_release_directory_placeholders_are_update_safe_but_runtime_storage_content_is_not(): void
    {
        $base = ['VERSION' => "1.2.0\n", 'artisan' => 'x', 'bootstrap/app.php' => 'x', 'public/index.php' => 'x', 'vendor/autoload.php' => 'x', 'public/build/manifest.json' => '{}'];
        $safe = $this->package([...$base, 'bootstrap/cache/.gitignore' => '', 'storage/app/private/.gitignore' => '']);
        $verified = (new ReleasePackageVerifier)->stage($safe, $this->metadata($safe), $this->directory.DIRECTORY_SEPARATOR.'safe-stage');
        $this->assertContains('storage/app/private/.gitignore', $verified->files);
        $verified->cleanup();

        $unsafe = $this->package([...$base, 'storage/app/private/member-photo.jpg' => 'private']);
        $this->expectException(RuntimeException::class);
        (new ReleasePackageVerifier)->stage($unsafe, $this->metadata($unsafe), $this->directory.DIRECTORY_SEPARATOR.'unsafe-stage');
    }

    /** @param array<string, string> $files */
    private function package(array $files): string
    {
        $path = $this->directory.DIRECTORY_SEPARATOR.'release-'.bin2hex(random_bytes(4)).'.zip';
        $manifest = ['format' => 1, 'version' => '1.2.0', 'files' => [], 'deletes' => ['obsolete.txt']];
        $archive = new ZipArchive;
        $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($files as $file => $contents) {
            $manifest['files'][] = ['path' => $file, 'sha256' => hash('sha256', $contents), 'size_bytes' => strlen($contents)];
            $archive->addFromString('application/'.$file, $contents);
        }
        $archive->addFromString('release-manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $archive->close();

        return $path;
    }

    private function metadata(string $package, ?string $sha256 = null): ReleaseMetadata
    {
        return new ReleaseMetadata('1.2.0', '2026-08-24T09:00:00Z', false, 'Release', [], 'https://updates.example.test/release.zip', $sha256 ?? hash_file('sha256', $package), filesize($package), ['php' => '8.3.0', 'extensions' => [], 'database' => ['mysql' => '8.0.0', 'mariadb' => '10.6.0'], 'disk_free_bytes' => 1]);
    }

    private function clean(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }
}
