<?php

namespace Tests\Unit\Operations;

use PHPUnit\Framework\TestCase;
use Waymark\Release\VerificationEnvironment;

require_once dirname(__DIR__, 3).'/scripts/release/VerificationEnvironment.php';

final class ReleaseVerificationEnvironmentTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/waymark-release-environment-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);
        file_put_contents($this->directory.'/.env.example', "APP_NAME=Waymark\nAPP_KEY=\nAPP_ENV=local\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->directory.'/.env');
        @unlink($this->directory.'/.env.example');
        @rmdir($this->directory);
        parent::tearDown();
    }

    public function test_it_creates_a_disposable_environment_with_a_fresh_application_key(): void
    {
        $example = file_get_contents($this->directory.'/.env.example');

        (new VerificationEnvironment)->prepare($this->directory);

        $environment = file_get_contents($this->directory.'/.env');
        $this->assertIsString($environment);
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:[A-Za-z0-9+\\/]{43}=$/m', $environment);
        $this->assertSame($example, file_get_contents($this->directory.'/.env.example'));
    }

    public function test_it_replaces_an_existing_disposable_environment_without_using_process_environment_keys(): void
    {
        file_put_contents($this->directory.'/.env', "APP_KEY=base64:stale\n");
        putenv('APP_KEY=base64:developer-machine-key');

        try {
            (new VerificationEnvironment)->prepare($this->directory);
        } finally {
            putenv('APP_KEY');
        }

        $environment = file_get_contents($this->directory.'/.env');
        $this->assertIsString($environment);
        $this->assertStringNotContainsString('stale', $environment);
        $this->assertStringNotContainsString('developer-machine-key', $environment);
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:[A-Za-z0-9+\\/]{43}=$/m', $environment);
    }
}
