<?php

namespace Tests\Unit\Operations;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Waymark\Release\ReleaseArchiveBuilder;
use ZipArchive;

require_once dirname(__DIR__, 3).'/scripts/release/ReleaseArchiveBuilder.php';

final class ReleaseArchiveBuilderTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/waymark-release-builder-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/application/public/build', 0700, true);
        mkdir($this->directory.'/application/vendor', 0700, true);
        file_put_contents($this->directory.'/application/VERSION', "1.0.0\n");
        file_put_contents($this->directory.'/application/artisan', 'artisan');
        file_put_contents($this->directory.'/application/public/index.php', 'front controller');
        file_put_contents($this->directory.'/application/public/build/manifest.json', '{}');
        file_put_contents($this->directory.'/application/vendor/autoload.php', 'autoload');
    }

    protected function tearDown(): void
    {
        $this->remove($this->directory);
        parent::tearDown();
    }

    public function test_builder_writes_deterministic_release_metadata_file_checksums_and_archive_checksum(): void
    {
        $output = $this->directory.'/waymark-community-1.0.0-shared-hosting.zip';
        $result = (new ReleaseArchiveBuilder)->build($this->directory.'/application', $output, [
            'version' => '1.0.0',
            'commit' => str_repeat('a', 40),
            'built_at' => '2026-08-24T12:00:00Z',
            'minimum_php' => '8.3.0',
            'schema' => '2026_08_24_130000',
        ]);

        $this->assertFileExists($output);
        $this->assertFileExists($output.'.sha256');
        $this->assertSame(hash_file('sha256', $output), $result['sha256']);
        $this->assertSame($result['sha256'].'  '.basename($output)."\n", file_get_contents($output.'.sha256'));

        $archive = new ZipArchive;
        $this->assertTrue($archive->open($output));
        $manifest = json_decode((string) $archive->getFromName('release-manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(1, $manifest['format']);
        $this->assertSame('1.0.0', $manifest['version']);
        $this->assertSame(str_repeat('a', 40), $manifest['build']['commit']);
        $this->assertSame('8.3.0', $manifest['requirements']['minimum_php']);
        $this->assertSame('2026_08_24_130000', $manifest['database']['latest_migration']);
        $this->assertSame([], $manifest['deletes']);

        foreach ($manifest['files'] as $file) {
            $contents = $archive->getFromName('application/'.$file['path']);
            $this->assertIsString($contents);
            $this->assertSame(hash('sha256', $contents), $file['sha256']);
            $this->assertSame(strlen($contents), $file['size_bytes']);
        }
        $this->assertNotFalse($archive->locateName('application/vendor/autoload.php'));
        $this->assertNotFalse($archive->locateName('application/public/build/manifest.json'));
        $archive->close();
    }

    public function test_builder_refuses_a_secret_environment_file_in_the_package_tree(): void
    {
        file_put_contents($this->directory.'/application/.env', 'APP_KEY=secret');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('forbidden');
        (new ReleaseArchiveBuilder)->build($this->directory.'/application', $this->directory.'/unsafe.zip', [
            'version' => '1.0.0',
            'commit' => str_repeat('a', 40),
            'built_at' => '2026-08-24T12:00:00Z',
            'minimum_php' => '8.3.0',
            'schema' => '2026_08_24_130000',
        ]);
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
