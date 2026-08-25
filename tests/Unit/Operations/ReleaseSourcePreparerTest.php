<?php

namespace Tests\Unit\Operations;

use PHPUnit\Framework\TestCase;
use Waymark\Release\ReleaseSourcePreparer;

final class ReleaseSourcePreparerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/waymark-release-source-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/source', 0700, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->directory);
        parent::tearDown();
    }

    public function test_preparer_copies_only_runtime_application_and_operator_material(): void
    {
        $class = dirname(__DIR__, 3).'/scripts/release/ReleaseSourcePreparer.php';
        if (is_file($class)) {
            require_once $class;
        }
        $this->assertTrue(class_exists(ReleaseSourcePreparer::class), 'The release source preparer is missing.');

        foreach ([
            'app/Runtime.php', 'bootstrap/app.php', 'config/app.php', 'database/migrations/release.php',
            'lang/en/messages.php', 'public/index.php', 'public/build/stale.js', 'resources/views/welcome.blade.php',
            'routes/web.php', 'storage/app/private/.gitignore', 'storage/app/private/runtime.txt',
            'docs/deployment/shared-hosting.md', 'docs/development/testing.md', 'tests/Feature/ExampleTest.php',
            'scripts/build-release.php', 'vendor/autoload.php', '.phpunit.result.cache', 'playwright.release.config.ts',
            'CODEX-FIRST-PROMPT.md', 'CONTRIBUTING.md', 'REPOSITORY-MANIFEST.md', '.editorconfig', '.npmrc',
            'package.json', 'package-lock.json', 'vite.config.js', 'AGENTS.md', 'START-HERE-FOR-CODEX.md',
        ] as $path) {
            $this->write('source/'.$path, $path);
        }
        foreach (['artisan', 'composer.json', 'composer.lock', 'CHANGELOG.md', 'SECURITY.md'] as $path) {
            $this->write('source/'.$path, $path);
        }
        $this->write('source/.env.example', "APP_ENV=local\nAPP_DEBUG=true\nAPP_KEY=developer\n");
        $this->write('source/README.md', "# Source checkout\nnpm ci\n");

        $environment = "APP_ENV=production\nAPP_KEY=\nAPP_DEBUG=false\nAPP_URL=https://your-domain.example\nLOG_LEVEL=warning\nDB_DATABASE=\nDB_USERNAME=\nDB_PASSWORD=\nMAIL_USERNAME=\nMAIL_PASSWORD=\nAWS_ACCESS_KEY_ID=\nAWS_SECRET_ACCESS_KEY=\nAWS_BUCKET=\nWAYMARK_RECOVERY_TOKEN_HASH=\nWAYMARK_RELEASE_METADATA_URL=\nWAYMARK_RELEASE_PUBLIC_KEY_BASE64=\nTURNSTILE_SITE_KEY=\nTURNSTILE_SECRET_KEY=\n";
        $readme = "# Waymark Community {{VERSION}}\n\nPoint the document root at public and visit /setup. PHP 8.3+, MySQL and MariaDB are supported. Composer and Node are not required at runtime. See docs/deployment and SECURITY.md.\n";

        (new ReleaseSourcePreparer)->prepare($this->directory.'/source', $this->directory.'/application', '1.0.0', $environment, $readme);

        foreach (['app/Runtime.php', 'public/index.php', 'docs/deployment/shared-hosting.md', 'storage/app/private/.gitignore', 'artisan', 'composer.json', 'composer.lock', 'CHANGELOG.md', 'SECURITY.md'] as $path) {
            $this->assertFileExists($this->directory.'/application/'.$path, $path);
        }
        foreach (['public/build/stale.js', 'storage/app/private/runtime.txt', 'docs/development/testing.md', 'tests/Feature/ExampleTest.php', 'scripts/build-release.php', 'vendor/autoload.php', '.phpunit.result.cache', 'playwright.release.config.ts', 'CODEX-FIRST-PROMPT.md', 'CONTRIBUTING.md', 'REPOSITORY-MANIFEST.md', '.editorconfig', '.npmrc', 'package.json', 'package-lock.json', 'vite.config.js', 'AGENTS.md', 'START-HERE-FOR-CODEX.md'] as $path) {
            $this->assertFileDoesNotExist($this->directory.'/application/'.$path, $path);
        }
        $this->assertSame($environment, file_get_contents($this->directory.'/application/.env.example'));
        $this->assertStringContainsString('Waymark Community 1.0.0', (string) file_get_contents($this->directory.'/application/README.md'));
        $this->assertStringNotContainsString('npm ci', (string) file_get_contents($this->directory.'/application/README.md'));
    }

    private function write(string $path, string $contents): void
    {
        $target = $this->directory.'/'.$path;
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
