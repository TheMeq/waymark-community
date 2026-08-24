<?php

namespace App\Domain\Operations\Updates\Actions;

use App\Domain\Operations\Backups\Actions\RestoreBackup;
use App\Domain\Operations\Backups\Models\BackupRun;
use App\Domain\Operations\Locks\DestructiveOperationLock;
use App\Domain\Operations\Updates\UpdateRuntimeBoundary;
use App\Domain\Operations\Updates\UpdateStateStore;
use RuntimeException;
use Throwable;

final readonly class FinalizeUpdateRollback
{
    public function __construct(
        private UpdateStateStore $state,
        private RestoreBackup $restore,
        private DestructiveOperationLock $operations,
        private UpdateRuntimeBoundary $runtimeBoundary,
    ) {}

    public function handle(string $rollbackToken): void
    {
        $this->operations->run('update-rollback', function () use ($rollbackToken): void {
            $pending = $this->pendingState($rollbackToken);
            if (hash_equals($pending['failed_runtime_id'], $this->runtimeBoundary->id)) {
                throw new RuntimeException('Update rollback must run in a fresh PHP request under the restored old release runtime.');
            }

            $backup = new BackupRun($pending['backup']);
            try {
                $this->restore->fromUpdateRollback($backup);
                $this->state->write([
                    ...$pending,
                    'status' => 'update_failed',
                    'rollback_complete' => true,
                    'rollback_runtime_id' => $this->runtimeBoundary->id,
                    'rollback_completed_at' => now('UTC')->toIso8601String(),
                    'message' => 'Fresh-runtime activation failed and the restored old release verified the rollback. Maintenance mode remains active for review.',
                ]);
            } catch (Throwable $exception) {
                $this->state->write([
                    ...$pending,
                    'rollback_failed_at' => now('UTC')->toIso8601String(),
                    'message' => 'The restored old release could not complete rollback. Maintenance and recovery material remain available.',
                ]);
                throw new RuntimeException('The restored old release could not complete rollback. Use disaster recovery.', previous: $exception);
            }
        });
    }

    /** @return array<string, mixed> */
    private function pendingState(string $rollbackToken): array
    {
        $state = $this->state->authorisedRollback($rollbackToken);
        $backup = $state['backup'] ?? null;
        if (! is_array($state) || ! is_string($state['failed_runtime_id'] ?? null) || strlen($state['failed_runtime_id']) !== 64
            || ! is_array($backup) || ($backup['status'] ?? null) !== 'completed'
            || ! is_string($backup['storage_disk'] ?? null) || ! is_string($backup['storage_path'] ?? null)
            || ! is_string($backup['sha256'] ?? null) || preg_match('/^[a-f0-9]{64}$/', $backup['sha256']) !== 1
            || ! is_int($backup['size_bytes'] ?? null) || ! is_bool($backup['encrypted'] ?? null)) {
            throw new RuntimeException('The pending update rollback could not be authorised.');
        }

        return $state;
    }
}
