<?php

namespace Tests\Unit\Operations;

use App\Domain\Operations\Backups\BackupEncryptor;
use PHPUnit\Framework\TestCase;

final class BackupEncryptionTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'waymark-encryption-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    public function test_optional_encryption_round_trips_in_bounded_chunks_and_rejects_wrong_passphrase(): void
    {
        $source = $this->directory.DIRECTORY_SEPARATOR.'backup.zip';
        $encrypted = $source.'.enc';
        $restored = $this->directory.DIRECTORY_SEPARATOR.'restored.zip';
        file_put_contents($source, str_repeat('backup-content-', 10000));
        $encryptor = new BackupEncryptor;

        $encryptor->encrypt($source, $encrypted, 'strong recovery phrase');

        $this->assertNotSame(substr((string) file_get_contents($source), 0, 32), substr((string) file_get_contents($encrypted), 0, 32));
        $this->assertTrue($encryptor->decrypt($encrypted, $restored, 'strong recovery phrase'));
        $this->assertSame(hash_file('sha256', $source), hash_file('sha256', $restored));
        $this->assertFalse($encryptor->decrypt($encrypted, $restored.'.wrong', 'wrong phrase'));
    }
}
