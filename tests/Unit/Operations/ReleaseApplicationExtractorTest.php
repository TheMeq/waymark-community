<?php

namespace Tests\Unit\Operations;

use PHPUnit\Framework\TestCase;
use Waymark\Release\ReleaseApplicationExtractor;
use Waymark\Release\ReleaseArchiveBuilder;

require_once dirname(__DIR__, 3).'/scripts/release/ReleaseArchiveBuilder.php';
require_once dirname(__DIR__, 3).'/scripts/release/ReleaseArchiveVerifier.php';
require_once dirname(__DIR__, 3).'/scripts/release/ReleaseApplicationExtractor.php';

final class ReleaseApplicationExtractorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/waymark-release-extractor-'.bin2hex(random_bytes(8));
        foreach ([
            '.env.example' => 'APP_KEY=',
            'VERSION' => "1.0.0\n",
            'artisan' => 'artisan',
            'bootstrap/app.php' => 'bootstrap',
            'bootstrap/cache/.gitignore' => '',
            'database/migrations/2026_08_24_130000_release.php' => 'migration',
            'public/index.php' => 'front',
            'public/build/manifest.json' => '{}',
            'storage/app/private/.gitignore' => '',
            'storage/framework/cache/.gitignore' => '',
            'vendor/autoload.php' => 'autoload',
            'vendor/composer/installed.json' => '{"packages":[]}',
        ] as $path => $contents) {
            $target = $this->directory.'/application/'.$path;
            if (! is_dir(dirname($target))) {
                mkdir(dirname($target), 0700, true);
            }
            file_put_contents($target, $contents);
        }
    }

    protected function tearDown(): void
    {
        $this->remove($this->directory);
        parent::tearDown();
    }

    public function test_extractor_verifies_the_release_then_places_only_application_contents_at_install_root(): void
    {
        $archive = $this->package();
        $destination = $this->directory.'/installed';

        $result = (new ReleaseApplicationExtractor)->extract($archive, $destination);

        $this->assertSame('1.0.0', $result['version']);
        $this->assertFileExists($destination.'/vendor/autoload.php');
        $this->assertFileExists($destination.'/public/build/manifest.json');
        $this->assertFileDoesNotExist($destination.'/release-manifest.json');
        $this->assertDirectoryDoesNotExist($destination.'/application');
        $this->assertFileDoesNotExist($destination.'/.env');
    }

    public function test_extractor_refuses_a_nonempty_destination(): void
    {
        $archive = $this->package();
        mkdir($this->directory.'/installed');
        file_put_contents($this->directory.'/installed/existing.txt', 'do not overwrite');

        $this->expectExceptionMessage('empty');
        (new ReleaseApplicationExtractor)->extract($archive, $this->directory.'/installed');
    }

    private function package(): string
    {
        $path = $this->directory.'/release.zip';
        (new ReleaseArchiveBuilder)->build($this->directory.'/application', $path, [
            'version' => '1.0.0',
            'commit' => str_repeat('c', 40),
            'built_at' => '2026-08-24T12:00:00Z',
            'minimum_php' => '8.3.0',
            'schema' => '2026_08_24_130000',
        ]);

        return $path;
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
