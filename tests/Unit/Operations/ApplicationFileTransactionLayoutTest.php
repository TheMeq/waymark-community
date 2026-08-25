<?php

namespace Tests\Unit\Operations;

use App\Domain\Operations\Updates\ApplicationFileTransaction;
use App\Domain\Operations\Updates\VerifiedReleasePackage;
use PHPUnit\Framework\TestCase;

final class ApplicationFileTransactionLayoutTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/waymark-layout-update-'.bin2hex(random_bytes(8));
        foreach (['webroot/application/bootstrap', 'stage/application/public', 'stage/application/app'] as $path) {
            mkdir($this->directory.'/'.$path, 0700, true);
        }
        file_put_contents($this->directory.'/webroot/application/artisan', 'artisan');
        file_put_contents($this->directory.'/webroot/application/bootstrap/app.php', 'old bootstrap');
        file_put_contents($this->directory.'/webroot/index.php', 'old public index');
        file_put_contents($this->directory.'/stage/application/public/index.php', 'new public index');
        file_put_contents($this->directory.'/stage/application/app/runtime.php', 'new internal runtime');
    }

    protected function tearDown(): void
    {
        $this->remove($this->directory);
        parent::tearDown();
    }

    public function test_public_html_update_keeps_public_and_internal_files_in_their_existing_roots(): void
    {
        $release = new VerifiedReleasePackage(
            $this->directory.'/stage',
            '1.2.0',
            ['public/index.php', 'app/runtime.php'],
            [],
            'public-html',
        );

        $transaction = (new ApplicationFileTransaction)->prepare(
            $release,
            $this->directory.'/webroot/application',
            $this->directory.'/rollback',
            $this->directory.'/webroot',
        );
        $transaction->apply();

        $this->assertSame('new public index', file_get_contents($this->directory.'/webroot/index.php'));
        $this->assertSame('new internal runtime', file_get_contents($this->directory.'/webroot/application/app/runtime.php'));
        $this->assertFileDoesNotExist($this->directory.'/webroot/application/public/index.php');

        $transaction->rollback();
        $this->assertSame('old public index', file_get_contents($this->directory.'/webroot/index.php'));
        $this->assertFileDoesNotExist($this->directory.'/webroot/application/app/runtime.php');
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
