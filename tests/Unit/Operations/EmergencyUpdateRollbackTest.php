<?php

namespace Tests\Unit\Operations;

use PHPUnit\Framework\TestCase;

final class EmergencyUpdateRollbackTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 3).'/public/waymark-update-recovery-runtime.php';
        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'waymark-emergency-update-'.bin2hex(random_bytes(8));
        mkdir($this->directory.DIRECTORY_SEPARATOR.'application', 0700, true);
        mkdir($this->directory.DIRECTORY_SEPARATOR.'rollback', 0700, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $item) {
                $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            }
            @rmdir($this->directory);
        }
        parent::tearDown();
    }

    public function test_standalone_runtime_restores_old_files_when_new_laravel_boot_cannot_run(): void
    {
        $application = $this->directory.DIRECTORY_SEPARATOR.'application';
        $rollback = $this->directory.DIRECTORY_SEPARATOR.'rollback';
        file_put_contents($application.DIRECTORY_SEPARATOR.'bootstrap.php', 'broken-new-runtime');
        file_put_contents($application.DIRECTORY_SEPARATOR.'added.php', 'new-file');
        file_put_contents($rollback.DIRECTORY_SEPARATOR.'bootstrap.php', 'working-old-runtime');
        $statePath = $this->directory.DIRECTORY_SEPARATOR.'update-state.json';
        file_put_contents($statePath, json_encode([
            'format' => 1,
            'status' => 'pending_activation',
            'activation_token_hash' => hash('sha256', 'private-activation-token'),
            'application_root' => $application,
            'rollback_directory' => $rollback,
            'rollback_records' => [
                ['path' => 'bootstrap.php', 'existed' => true, 'permissions' => 0644],
                ['path' => 'added.php', 'existed' => false, 'permissions' => null],
            ],
        ], JSON_THROW_ON_ERROR));

        \WaymarkEmergencyUpdateRollback::restore($statePath, 'private-activation-token');

        $this->assertSame('working-old-runtime', file_get_contents($application.DIRECTORY_SEPARATOR.'bootstrap.php'));
        $this->assertFileDoesNotExist($application.DIRECTORY_SEPARATOR.'added.php');
        $this->assertSame('boot_rollback_completed', json_decode(file_get_contents($statePath), true, flags: JSON_THROW_ON_ERROR)['status']);
    }
}
