<?php

namespace Tests\Unit\Operations;

use PHPUnit\Framework\Attributes\DataProvider;
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
        mkdir($this->directory.'/application/bootstrap', 0700, true);
        file_put_contents($this->directory.'/application/VERSION', "1.0.0\n");
        file_put_contents($this->directory.'/application/DEPLOYMENT-LAYOUT', "standard\n");
        file_put_contents($this->directory.'/application/artisan', 'artisan');
        file_put_contents($this->directory.'/application/public/index.php', 'front controller');
        file_put_contents($this->directory.'/application/public/build/manifest.json', '{}');
        file_put_contents($this->directory.'/application/vendor/autoload.php', 'autoload');
        file_put_contents($this->directory.'/application/bootstrap/app.php', 'bootstrap');
        file_put_contents($this->directory.'/application/.env.example', "APP_ENV=production\nAPP_KEY=\nAPP_DEBUG=false\nAPP_URL=https://your-domain.example\nLOG_LEVEL=warning\nDB_PASSWORD=\nMAIL_PASSWORD=\nTURNSTILE_SECRET_KEY=\n");
        file_put_contents($this->directory.'/application/README.md', "# Waymark Community 1.0.0\n\nPoint the document root at public, then visit /setup. PHP 8.3+ and MySQL 8.4 or MariaDB 11.4 are required. Composer and Node.js are not required at runtime. See docs/deployment and SECURITY.md.\n");
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
        $this->assertSame('standard', $manifest['layout']);
        $this->assertSame($result['application_file_count'], $manifest['application_file_count']);
        $archive->close();
    }

    #[DataProvider('waymarkDevelopmentFiles')]
    public function test_builder_refuses_waymark_owned_development_files(string $path): void
    {
        file_put_contents($this->directory.'/application/'.$path, 'developer-only');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('forbidden');
        $this->package('unsafe.zip');
    }

    public static function waymarkDevelopmentFiles(): array
    {
        return [
            ['.phpunit.result.cache'],
            ['playwright.config.ts'],
            ['playwright.release.config.ts'],
            ['playwright.staging.config.ts'],
            ['CODEX-FIRST-PROMPT.md'],
            ['CONTRIBUTING.md'],
            ['REPOSITORY-MANIFEST.md'],
            ['AGENTS.md'],
            ['START-HERE-FOR-CODEX.md'],
            ['.editorconfig'],
            ['.npmrc'],
            ['package.json'],
            ['package-lock.json'],
            ['vite.config.js'],
            ['developer-notes.txt'],
            ['public/.env'],
        ];
    }

    public function test_builder_refuses_a_release_environment_template_with_unsafe_defaults_or_secrets(): void
    {
        file_put_contents($this->directory.'/application/.env.example', "APP_ENV=local\nAPP_KEY=secret\nAPP_DEBUG=true\nAPP_URL=http://localhost\nLOG_LEVEL=debug\nDB_PASSWORD=secret\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('configuration template');
        $this->package('unsafe-environment.zip');
    }

    public function test_builder_refuses_the_source_development_readme_as_the_package_readme(): void
    {
        file_put_contents($this->directory.'/application/README.md', "# Waymark source checkout\n\nnpm ci\ncomposer install\nPhase 10 acceptance\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('operator README');
        $this->package('source-readme.zip');
    }

    public function test_builder_creates_a_protected_public_html_layout_without_flattening_the_application(): void
    {
        file_put_contents($this->directory.'/application/DEPLOYMENT-LAYOUT', "public-html\n");
        file_put_contents($this->directory.'/application/.htaccess', "Options -Indexes\nRequire all denied\nDeny from all\n");
        file_put_contents($this->directory.'/application/public/.htaccess', "Options -Indexes\nRewriteEngine On\nRewriteRule ^ index.php [L]\n");
        mkdir($this->directory.'/application/public/images', 0700, true);
        file_put_contents($this->directory.'/application/public/images/icon.png', 'icon');
        file_put_contents($this->directory.'/application/public/index.php', "<?php require __DIR__.'/application/vendor/autoload.php'; \$app->usePublicPath(__DIR__);");
        file_put_contents($this->directory.'/application/public/README.md', "# Waymark Community 1.0.0 public_html\n\nExtract into the document root and visit /setup. PHP 8.3+, MySQL and MariaDB are supported. Composer and Node are not required at runtime. See docs/deployment and SECURITY.md.\n");

        $output = $this->package('waymark-community-1.0.0-public-html.zip', 'public-html');

        $archive = new ZipArchive;
        $this->assertTrue($archive->open($output));
        $manifest = json_decode((string) $archive->getFromName('release-manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('public-html', $manifest['layout']);
        foreach (['index.php', '.htaccess', 'build/manifest.json', 'images/icon.png', 'README.md', 'application/.htaccess', 'application/.env.example', 'application/vendor/autoload.php', 'application/bootstrap/app.php'] as $entry) {
            $this->assertNotFalse($archive->locateName($entry), $entry);
        }
        $this->assertFalse($archive->locateName('application/public/index.php'));
        $this->assertFalse($archive->locateName('public/index.php'));
        $archive->close();
    }

    public function test_builder_refuses_a_layout_marker_that_does_not_match_the_archive_layout(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('layout marker');
        $this->package('mismatched-layout.zip', 'public-html');
    }

    private function package(string $name, string $layout = 'standard'): string
    {
        $path = $this->directory.'/'.$name;
        (new ReleaseArchiveBuilder)->build($this->directory.'/application', $path, [
            'version' => '1.0.0',
            'commit' => str_repeat('a', 40),
            'built_at' => '2026-08-24T12:00:00Z',
            'minimum_php' => '8.3.0',
            'schema' => '2026_08_24_130000',
            'layout' => $layout,
        ]);

        return $path;
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
