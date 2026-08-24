<?php

namespace Tests\Unit\Operations;

use App\Domain\Operations\Backups\RestorationEnvironmentPolicy;
use PHPUnit\Framework\TestCase;

final class RestorationEnvironmentPolicyTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'waymark-restoration-policy-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.DIRECTORY_SEPARATOR.'*') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($this->directory);
        parent::tearDown();
    }

    public function test_export_contains_only_allowlisted_cryptographic_identity(): void
    {
        $source = $this->directory.DIRECTORY_SEPARATOR.'source.env';
        $portable = $this->directory.DIRECTORY_SEPARATOR.'restoration.env';
        file_put_contents($source, implode("\n", [
            'APP_KEY=base64:source-key',
            'APP_PREVIOUS_KEYS="base64:older-key,base64:oldest-key"',
            'DB_HOST=source-db.internal',
            'DB_PASSWORD=source-password',
            'APP_URL=https://source.example',
            'FILESYSTEM_DISK=source-private',
        ])."\n");

        (new RestorationEnvironmentPolicy)->export($source, $portable);

        $this->assertSame(
            "APP_KEY=base64:source-key\nAPP_PREVIOUS_KEYS=\"base64:older-key,base64:oldest-key\"\n",
            file_get_contents($portable),
        );
    }

    public function test_merge_restores_allowlisted_identity_and_preserves_target_host_configuration(): void
    {
        $portable = $this->directory.DIRECTORY_SEPARATOR.'restoration.env';
        $target = $this->directory.DIRECTORY_SEPARATOR.'target.env';
        file_put_contents($portable, "APP_KEY=base64:source-key\nAPP_PREVIOUS_KEYS=base64:older-key\nDB_HOST=untrusted-source\n");
        file_put_contents($target, implode("\n", [
            'APP_KEY=base64:temporary-target-key',
            'APP_PREVIOUS_KEYS=',
            'APP_URL=https://target.example',
            'DB_HOST=target-db.internal',
            'DB_PORT=3307',
            'DB_DATABASE=target_waymark',
            'DB_USERNAME=target_user',
            'DB_PASSWORD=target-password',
            'FILESYSTEM_DISK=local',
            'CACHE_STORE=file',
            'SESSION_DRIVER=database',
            'QUEUE_CONNECTION=sync',
        ])."\n");

        (new RestorationEnvironmentPolicy)->merge($portable, $target);

        $restored = (string) file_get_contents($target);
        $this->assertStringContainsString('APP_KEY=base64:source-key', $restored);
        $this->assertStringContainsString('APP_PREVIOUS_KEYS=base64:older-key', $restored);
        $this->assertStringContainsString('APP_URL=https://target.example', $restored);
        $this->assertStringContainsString('DB_HOST=target-db.internal', $restored);
        $this->assertStringContainsString('DB_PASSWORD=target-password', $restored);
        $this->assertStringContainsString('FILESYSTEM_DISK=local', $restored);
        $this->assertStringNotContainsString('untrusted-source', $restored);
    }
}
