<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Backups\Actions\CreateBackup;
use App\Domain\Operations\Backups\BackupVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

final class BackupIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private string $environmentPath;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('backups');
        $this->environmentPath = storage_path('framework/testing/integrity-environment-'.bin2hex(random_bytes(8)));
        file_put_contents($this->environmentPath, "APP_KEY=base64:integrity-key\n");
        config()->set('waymark.backups.environment_path', $this->environmentPath);
        config()->set('waymark.backups.destination_disk', 'backups');
    }

    protected function tearDown(): void
    {
        @unlink($this->environmentPath);
        parent::tearDown();
    }

    public function test_created_backup_has_verified_expected_components_and_checksums(): void
    {
        Storage::disk('local')->put('documents/policy.pdf', 'document-content');
        $backup = app(CreateBackup::class)->handle('manual', 'strong recovery phrase');

        $verified = app(BackupVerifier::class)->open(
            Storage::disk('backups')->path($backup->storage_path),
            'strong recovery phrase',
        );

        $this->assertSame(1, $verified->manifest['format']);
        $this->assertContains('database.jsonl', $verified->componentPaths());
        $this->assertContains('configuration/.env', $verified->componentPaths());
        $this->assertContains('private/documents/policy.pdf', $verified->componentPaths());
        $verified->cleanup();
    }

    public function test_tampered_component_is_rejected_before_restore(): void
    {
        $backup = app(CreateBackup::class)->handle('manual');
        $path = Storage::disk('backups')->path($backup->storage_path);
        $archive = new ZipArchive;
        $this->assertTrue($archive->open($path));
        $archive->addFromString('database.jsonl', "tampered\n");
        $archive->close();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('integrity');
        app(BackupVerifier::class)->open($path);
    }

    public function test_archive_with_traversal_entry_is_rejected(): void
    {
        $path = storage_path('framework/testing/unsafe-backup-'.bin2hex(random_bytes(8)).'.zip');
        $archive = new ZipArchive;
        $this->assertTrue($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $archive->addFromString('manifest.json', json_encode([
            'format' => 1,
            'components' => [
                ['path' => 'database.jsonl', 'sha256' => hash('sha256', ''), 'size_bytes' => 0],
                ['path' => 'configuration/.env', 'sha256' => hash('sha256', ''), 'size_bytes' => 0],
            ],
        ], JSON_THROW_ON_ERROR));
        $archive->addFromString('database.jsonl', '');
        $archive->addFromString('configuration/.env', '');
        $archive->addFromString('../outside.txt', 'unsafe');
        $archive->close();

        try {
            $this->expectException(RuntimeException::class);
            app(BackupVerifier::class)->open($path);
        } finally {
            @unlink($path);
        }
    }
}
