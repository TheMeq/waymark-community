<?php

namespace Tests\Unit\Operations;

use PHPUnit\Framework\Attributes\DataProvider;
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
            'bootstrap/cache/packages.php',
            'bootstrap/cache/services.php',
            'bootstrap/app.php',
            'database/migrations/2026_08_24_130000_release.php',
            'public/build/manifest.json',
            'public/index.php',
            'storage/app/.gitignore',
            'storage/app/private/.gitignore',
            'storage/app/public/.gitignore',
            'storage/framework/.gitignore',
            'storage/framework/cache/.gitignore',
            'storage/framework/cache/data/.gitignore',
            'storage/framework/sessions/.gitignore',
            'storage/framework/testing/.gitignore',
            'storage/framework/views/.gitignore',
            'storage/logs/.gitignore',
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
        foreach ([
            'DEPLOYMENT-LAYOUT' => "standard\n",
            'VERSION' => "1.0.0\n",
            'artisan' => 'artisan',
            '.env.example' => "APP_ENV=production\nAPP_KEY=\nAPP_DEBUG=false\nAPP_URL=https://your-domain.example\nLOG_LEVEL=warning\nDB_PASSWORD=\nMAIL_PASSWORD=\nTURNSTILE_SECRET_KEY=\n",
            'README.md' => "# Waymark Community 1.0.0\n\nPoint the document root at public, then visit /setup. PHP 8.3+ and MySQL 8.4 or MariaDB 11.4 are required. Composer and Node.js are not required at runtime. See docs/deployment and SECURITY.md.\n",
        ] as $path => $contents) {
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
        $this->assertSame('standard', $result['layout']);
        $this->assertSame(24, $result['application_file_count']);
        $this->assertSame(25, $result['archive_entry_count']);
        $this->assertSame(hash_file('sha256', $archive), $result['sha256']);
    }

    #[DataProvider('waymarkDevelopmentFiles')]
    public function test_verifier_rejects_a_manifest_declared_waymark_development_file(string $path): void
    {
        $archive = $this->package();
        $this->declareExtraFile($archive, $path, 'developer-only');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('verification failed');
        (new ReleaseArchiveVerifier)->verify($archive);
    }

    public static function waymarkDevelopmentFiles(): array
    {
        return ReleaseArchiveBuilderTest::waymarkDevelopmentFiles();
    }

    public function test_verifier_rejects_unsafe_environment_defaults_even_when_manifest_hashes_match(): void
    {
        $archive = $this->package();
        $this->replaceDeclaredFile($archive, '.env.example', "APP_ENV=local\nAPP_KEY=secret\nAPP_DEBUG=true\nLOG_LEVEL=debug\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('verification failed');
        (new ReleaseArchiveVerifier)->verify($archive);
    }

    public function test_verifier_rejects_a_source_development_readme_even_when_manifest_hashes_match(): void
    {
        $archive = $this->package();
        $this->replaceDeclaredFile($archive, 'README.md', "# Source checkout\ncomposer install\nnpm ci\nPhase 10 acceptance\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('verification failed');
        (new ReleaseArchiveVerifier)->verify($archive);
    }

    public function test_verifier_accepts_the_protected_public_html_layout_and_reports_exact_root_evidence(): void
    {
        $this->write('DEPLOYMENT-LAYOUT', "public-html\n");
        $this->write('.htaccess', "Options -Indexes\nRequire all denied\nDeny from all\n");
        $this->write('public/.htaccess', "Options -Indexes\nRewriteEngine On\nRewriteRule ^ index.php [L]\n");
        $this->write('public/index.php', "<?php require __DIR__.'/application/vendor/autoload.php'; \$app->usePublicPath(__DIR__);");
        $this->write('public/README.md', "# Waymark Community 1.0.0 public_html\n\nExtract into the document root and visit /setup. PHP 8.3+, MySQL and MariaDB are supported. Composer and Node are not required at runtime. See docs/deployment and SECURITY.md.\n");

        $archive = $this->package('public-html');
        $result = (new ReleaseArchiveVerifier)->verify($archive);

        $this->assertSame('public-html', $result['layout']);
        $this->assertSame($result['application_file_count'] + 1, $result['archive_entry_count']);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($archive));
        $this->assertNotFalse($zip->locateName('index.php'));
        $this->assertNotFalse($zip->locateName('application/.htaccess'));
        $this->assertFalse($zip->locateName('application/public/index.php'));
        $zip->close();
    }

    public function test_verifier_rejects_public_html_without_internal_access_denial(): void
    {
        $this->write('DEPLOYMENT-LAYOUT', "public-html\n");
        $this->write('.htaccess', "Options -Indexes\n");
        $this->write('public/.htaccess', "Options -Indexes\nRewriteEngine On\nRewriteRule ^ index.php [L]\n");
        $this->write('public/index.php', "<?php require __DIR__.'/application/vendor/autoload.php'; \$app->usePublicPath(__DIR__);");
        $this->write('public/README.md', "# Waymark Community 1.0.0 public_html\n\nExtract into the document root and visit /setup. PHP 8.3+, MySQL and MariaDB are supported. Composer and Node are not required at runtime. See docs/deployment and SECURITY.md.\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('verification failed');
        (new ReleaseArchiveVerifier)->verify($this->package('public-html'));
    }

    public function test_verifier_rejects_a_layout_marker_that_does_not_match_manifest_layout(): void
    {
        $this->write('DEPLOYMENT-LAYOUT', "public-html\n");
        $this->write('.htaccess', "Options -Indexes\nRequire all denied\nDeny from all\n");
        $this->write('public/.htaccess', "Options -Indexes\nRewriteEngine On\nRewriteRule ^ index.php [L]\n");
        $this->write('public/index.php', "<?php require __DIR__.'/application/vendor/autoload.php'; \$app->usePublicPath(__DIR__);");
        $this->write('public/README.md', "# Waymark Community 1.0.0 public_html\n\nExtract into the document root and visit /setup. PHP 8.3+, MySQL and MariaDB are supported. Composer and Node are not required at runtime. See docs/deployment and SECURITY.md.\n");

        $archive = $this->package('public-html');
        $this->replaceDeclaredFile($archive, 'DEPLOYMENT-LAYOUT', "standard\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('verification failed');
        (new ReleaseArchiveVerifier)->verify($archive);
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

    private function package(string $layout = 'standard'): string
    {
        $path = $this->directory.'/release.zip';
        (new ReleaseArchiveBuilder)->build($this->directory.'/application', $path, [
            'version' => '1.0.0',
            'commit' => str_repeat('b', 40),
            'built_at' => '2026-08-24T12:00:00Z',
            'minimum_php' => '8.3.0',
            'schema' => '2026_08_24_130000',
            'layout' => $layout,
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

    private function declareExtraFile(string $archivePath, string $path, string $contents): void
    {
        $archive = new ZipArchive;
        $this->assertTrue($archive->open($archivePath));
        $manifest = json_decode((string) $archive->getFromName('release-manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $manifest['files'][] = ['path' => $path, 'sha256' => hash('sha256', $contents), 'size_bytes' => strlen($contents)];
        $manifest['application_file_count']++;
        $archive->addFromString('application/'.$path, $contents);
        $archive->addFromString('release-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        $archive->close();
        file_put_contents($archivePath.'.sha256', hash_file('sha256', $archivePath).'  '.basename($archivePath)."\n");
    }

    private function replaceDeclaredFile(string $archivePath, string $path, string $contents): void
    {
        $archive = new ZipArchive;
        $this->assertTrue($archive->open($archivePath));
        $manifest = json_decode((string) $archive->getFromName('release-manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach ($manifest['files'] as &$file) {
            if ($file['path'] === $path) {
                $file['sha256'] = hash('sha256', $contents);
                $file['size_bytes'] = strlen($contents);
            }
        }
        unset($file);
        $archive->addFromString('application/'.$path, $contents);
        $archive->addFromString('release-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        $archive->close();
        file_put_contents($archivePath.'.sha256', hash_file('sha256', $archivePath).'  '.basename($archivePath)."\n");
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
