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
            '.env.example' => "APP_ENV=production\nAPP_KEY=\nAPP_DEBUG=false\nAPP_URL=https://walks.example\nLOG_LEVEL=warning\n",
            'README.md' => $this->operatorReadme(),
            'VERSION' => "1.0.0\n",
            'DEPLOYMENT-LAYOUT' => "standard\n",
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

    public function test_public_html_extractor_places_public_files_at_web_root_and_internals_under_application(): void
    {
        file_put_contents($this->directory.'/application/DEPLOYMENT-LAYOUT', "public-html\n");
        file_put_contents($this->directory.'/application/.htaccess', "Options -Indexes\nRequire all denied\nDeny from all\n");
        file_put_contents($this->directory.'/application/public/.htaccess', "Options -Indexes\n<FilesMatch \"^\\.\">\nRequire all denied\nDeny from all\n</FilesMatch>\nRewriteEngine On\n");
        file_put_contents($this->directory.'/application/public/index.php', "<?php __DIR__.'/application'; usePublicPath(__DIR__);");
        file_put_contents($this->directory.'/application/public/README.md', $this->operatorReadme());
        $destination = $this->directory.'/public-html';

        $result = (new ReleaseApplicationExtractor)->extract($this->package('public-html'), $destination);

        $this->assertSame('public-html', $result['layout']);
        $this->assertFileExists($destination.'/index.php');
        $this->assertFileExists($destination.'/.htaccess');
        $this->assertFileExists($destination.'/application/vendor/autoload.php');
        $this->assertFileDoesNotExist($destination.'/application/public/index.php');
        $this->assertFileDoesNotExist($destination.'/release-manifest.json');
    }

    private function package(string $layout = 'standard'): string
    {
        $path = $this->directory.'/release.zip';
        (new ReleaseArchiveBuilder)->build($this->directory.'/application', $path, [
            'version' => '1.0.0',
            'commit' => str_repeat('c', 40),
            'built_at' => '2026-08-24T12:00:00Z',
            'minimum_php' => '8.3.0',
            'schema' => '2026_08_24_130000',
            'layout' => $layout,
        ]);

        return $path;
    }

    private function operatorReadme(): string
    {
        return 'Waymark Community 1.0.0 requires PHP 8.3, MySQL or MariaDB. Configure the document root, visit /setup, and see docs/deployment plus security guidance. Composer and Node are not required.';
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
