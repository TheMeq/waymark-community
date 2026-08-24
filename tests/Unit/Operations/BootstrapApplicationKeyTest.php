<?php

namespace Tests\Unit\Operations;

use App\Domain\Operations\Installation\BootstrapApplicationKey;
use PHPUnit\Framework\TestCase;

final class BootstrapApplicationKeyTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'waymark-bootstrap-key-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $path = $this->temporaryDirectory.DIRECTORY_SEPARATOR.'setup.key';
        if (is_file($path)) {
            unlink($path);
        }
        if (is_dir($this->temporaryDirectory)) {
            rmdir($this->temporaryDirectory);
        }

        parent::tearDown();
    }

    public function test_an_explicit_application_key_is_preserved_without_writing_a_bootstrap_key(): void
    {
        $key = BootstrapApplicationKey::resolve('base64:configured-key', $this->path());

        $this->assertSame('base64:configured-key', $key);
        $this->assertFileDoesNotExist($this->path());
    }

    public function test_a_private_stable_key_is_created_for_pre_database_setup_sessions(): void
    {
        $first = BootstrapApplicationKey::resolve(null, $this->path());
        $second = BootstrapApplicationKey::resolve(null, $this->path());

        $this->assertStringStartsWith('base64:', (string) $first);
        $this->assertSame($first, $second);
        $this->assertFileExists($this->path());
        $this->assertSame(32, strlen((string) base64_decode(substr((string) $first, 7), true)));
    }

    private function path(): string
    {
        return $this->temporaryDirectory.DIRECTORY_SEPARATOR.'setup.key';
    }
}
