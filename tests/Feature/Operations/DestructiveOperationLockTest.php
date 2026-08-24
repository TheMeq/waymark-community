<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Backups\Actions\CreateBackup;
use App\Domain\Operations\Locks\DestructiveOperationLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

final class DestructiveOperationLockTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('backups');
        $this->directory = storage_path('framework/testing/operation-lock-'.bin2hex(random_bytes(8)));
        mkdir($this->directory, 0700, true);
        config()->set('waymark.operations.lock_path', $this->directory.DIRECTORY_SEPARATOR.'operation.lock');
        config()->set('waymark.operations.state_path', $this->directory.DIRECTORY_SEPARATOR.'operation.json');
        config()->set('waymark.operations.journal_path', $this->directory.DIRECTORY_SEPARATOR.'journal.jsonl');
        config()->set('waymark.backups.environment_path', $this->directory.DIRECTORY_SEPARATOR.'.env');
        config()->set('waymark.backups.destination_disk', 'backups');
        file_put_contents($this->directory.DIRECTORY_SEPARATOR.'.env', "APP_KEY=base64:lock-test\n");
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
        parent::tearDown();
    }

    public function test_concurrent_destructive_operation_is_refused_before_backup_work_starts(): void
    {
        $handle = fopen(config('waymark.operations.lock_path'), 'c+');
        $this->assertTrue(flock($handle, LOCK_EX | LOCK_NB));

        try {
            app(CreateBackup::class)->handle('manual');
            $this->fail('A concurrent backup unexpectedly started.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('already in progress', $exception->getMessage());
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        $this->assertDatabaseCount('backup_runs', 0);
    }

    public function test_operation_state_is_cleared_and_meaningful_success_or_failure_is_journalled(): void
    {
        $lock = app(DestructiveOperationLock::class);
        $this->assertSame('done', $lock->run('controlled-success', fn (): string => 'done'));
        $this->assertFileDoesNotExist(config('waymark.operations.state_path'));

        try {
            $lock->run('controlled-failure', fn () => throw new RuntimeException('secret technical detail'));
        } catch (RuntimeException) {
        }

        $journal = (string) file_get_contents(config('waymark.operations.journal_path'));
        $this->assertStringContainsString('controlled-success', $journal);
        $this->assertStringContainsString('completed', $journal);
        $this->assertStringContainsString('controlled-failure', $journal);
        $this->assertStringContainsString('failed', $journal);
        $this->assertStringNotContainsString('secret technical detail', $journal);
    }
}
