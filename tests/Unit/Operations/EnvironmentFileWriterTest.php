<?php

namespace Tests\Unit\Operations;

use App\Domain\Operations\Installation\EnvironmentFileWriter;
use PHPUnit\Framework\TestCase;

final class EnvironmentFileWriterTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'waymark-env-'.bin2hex(random_bytes(8));
        mkdir($this->temporaryDirectory);
        file_put_contents($this->temporaryDirectory.DIRECTORY_SEPARATOR.'.env.example', "APP_NAME=Waymark\nAPP_KEY=\nAPP_DEBUG=true\n");
    }

    protected function tearDown(): void
    {
        foreach (['.env', '.env.tmp', '.env.example', 'not-a-directory'] as $file) {
            $path = $this->temporaryDirectory.DIRECTORY_SEPARATOR.$file;
            if (is_file($path)) {
                unlink($path);
            }
        }

        if (is_dir($this->temporaryDirectory)) {
            rmdir($this->temporaryDirectory);
        }

        parent::tearDown();
    }

    public function test_environment_file_is_written_atomically_with_safely_quoted_values(): void
    {
        $writer = new EnvironmentFileWriter(
            $this->temporaryDirectory.DIRECTORY_SEPARATOR.'.env',
            $this->temporaryDirectory.DIRECTORY_SEPARATOR.'.env.example',
        );

        $result = $writer->write([
            'APP_NAME' => 'Peak Pathfinders',
            'APP_KEY' => 'base64:test/key=',
            'APP_DEBUG' => 'false',
            'DB_PASSWORD' => 'secret with spaces & symbols',
            'WAYMARK_INSTALLED' => 'false',
        ]);

        $this->assertTrue($result->written);
        $this->assertFileExists($this->temporaryDirectory.DIRECTORY_SEPARATOR.'.env');
        $this->assertFileDoesNotExist($this->temporaryDirectory.DIRECTORY_SEPARATOR.'.env.tmp');
        $this->assertStringContainsString('APP_NAME="Peak Pathfinders"', $result->contents);
        $this->assertStringContainsString('DB_PASSWORD="secret with spaces & symbols"', $result->contents);
        $this->assertStringContainsString('APP_DEBUG=false', $result->contents);
    }

    public function test_unwritable_destination_returns_copyable_content_and_plain_instructions(): void
    {
        $blockedParent = $this->temporaryDirectory.DIRECTORY_SEPARATOR.'not-a-directory';
        file_put_contents($blockedParent, 'blocking file');
        $writer = new EnvironmentFileWriter(
            $blockedParent.DIRECTORY_SEPARATOR.'.env',
            $this->temporaryDirectory.DIRECTORY_SEPARATOR.'.env.example',
        );

        $result = $writer->write(['APP_KEY' => 'base64:manual-key=', 'APP_DEBUG' => 'false']);

        $this->assertFalse($result->written);
        $this->assertStringContainsString('APP_KEY=base64:manual-key=', $result->contents);
        $this->assertStringContainsString('Create a file named .env', $result->instructions);
    }
}
