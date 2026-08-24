<?php

namespace App\Domain\Operations\Updates\Actions;

use App\Domain\Operations\Locks\DestructiveOperationLock;
use App\Domain\Operations\Maintenance\MaintenanceManager;
use App\Domain\Operations\Updates\AppliedUpdate;
use App\Domain\Operations\Updates\Contracts\UpdateRuntime;
use App\Domain\Operations\Updates\PendingUpdateRollback;
use App\Domain\Operations\Updates\PreparedApplicationUpdate;
use App\Domain\Operations\Updates\UpdateRuntimeBoundary;
use App\Domain\Operations\Updates\UpdateStateStore;
use RuntimeException;
use Throwable;

final readonly class FinalizeUpdate
{
    public function __construct(
        private UpdateRuntime $runtime,
        private UpdateStateStore $state,
        private MaintenanceManager $maintenance,
        private DestructiveOperationLock $operations,
        private UpdateRuntimeBoundary $runtimeBoundary,
    ) {}

    public function handle(string $activationToken): AppliedUpdate
    {
        return $this->operations->run('update-activation', fn (): AppliedUpdate => $this->activate($activationToken));
    }

    private function activate(string $activationToken): AppliedUpdate
    {
        $pending = $this->pendingState($activationToken);
        if (hash_equals($pending['initiating_runtime_id'], $this->runtimeBoundary->id)) {
            throw new RuntimeException('Update activation must run in a fresh PHP request under the new release runtime.');
        }
        $version = $pending['pending_version'];
        $transaction = PreparedApplicationUpdate::resumeRollback(
            $pending['application_root'],
            $pending['rollback_directory'],
            $pending['rollback_records'],
        );
        try {
            $this->runtime->activate($version, $pending['application_root']);
            $this->state->write([
                ...$pending,
                'status' => 'activation_succeeded',
                'activated_at' => now('UTC')->toIso8601String(),
                'message' => 'The new release passed fresh-runtime activation.',
            ]);
            $this->cleanDirectory($pending['operation_directory']);
            $this->state->write([
                'status' => 'installed',
                'checked_at' => now('UTC')->toIso8601String(),
                'installed_version' => $version,
                'message' => 'The verified stable release was installed successfully.',
            ]);
            if (! $pending['maintenance_was_active']) {
                $this->maintenance->disable();
            }

            return new AppliedUpdate($version);
        } catch (Throwable $activationFailure) {
            $filesRestored = true;
            try {
                $transaction->rollback();
            } catch (Throwable $fileRollbackFailure) {
                report($fileRollbackFailure);
                $filesRestored = false;
            }
            $rollbackToken = bin2hex(random_bytes(32));
            try {
                $this->state->write([
                    ...$pending,
                    'status' => $filesRestored ? 'pending_rollback' : 'rollback_failed',
                    'failed_at' => now('UTC')->toIso8601String(),
                    'failed_runtime_id' => $this->runtimeBoundary->id,
                    'rollback_token_hash' => hash('sha256', $rollbackToken),
                    'application_files_restored' => $filesRestored,
                    'rollback_complete' => false,
                    'message' => $filesRestored
                        ? 'Fresh-runtime activation failed. Old application files are restored and await fresh old-runtime database rollback.'
                        : 'Fresh-runtime activation failed and old application files could not be restored. Use emergency recovery.',
                ]);
            } catch (Throwable $stateFailure) {
                report($stateFailure);
            }
            report($activationFailure);

            if ($filesRestored) {
                throw new PendingUpdateRollback($rollbackToken);
            }

            throw new RuntimeException('The update failed under the fresh release runtime and old application files could not be restored. Maintenance mode remains active; use emergency recovery.', previous: $activationFailure);
        }
    }

    /** @return array{pending_version: string, activation_token_hash: string, initiating_runtime_id: string, application_root: string, operation_directory: string, rollback_directory: string, rollback_records: list<array{path: string, existed: bool, permissions: int|null}>, backup_id: int, maintenance_was_active: bool} */
    private function pendingState(string $activationToken): array
    {
        $state = $this->state->authorisedPending($activationToken);
        if (! is_array($state)) {
            throw new RuntimeException('The pending update activation could not be authorised.');
        }

        $applicationRoot = realpath((string) ($state['application_root'] ?? ''));
        $configuredRoot = realpath((string) config('waymark.updates.application_root', base_path()));
        $operationDirectory = realpath((string) ($state['operation_directory'] ?? ''));
        $rollbackDirectory = realpath((string) ($state['rollback_directory'] ?? ''));
        $stagingRoot = realpath((string) config('waymark.updates.staging_root', storage_path('framework/update-staging')));
        $records = $state['rollback_records'] ?? null;
        if (! is_string($applicationRoot) || $applicationRoot !== $configuredRoot
            || ! is_string($operationDirectory) || ! is_string($stagingRoot)
            || ! str_starts_with($operationDirectory, rtrim($stagingRoot, '\\/').DIRECTORY_SEPARATOR)
            || ! is_string($rollbackDirectory) || ! str_starts_with($rollbackDirectory, $operationDirectory.DIRECTORY_SEPARATOR)
            || ! is_array($records) || ! is_int($state['backup_id'] ?? null)
            || ! is_string($state['pending_version'] ?? null) || ! is_bool($state['maintenance_was_active'] ?? null)
            || ! is_string($state['initiating_runtime_id'] ?? null) || strlen($state['initiating_runtime_id']) !== 64) {
            throw new RuntimeException('The pending update activation state is invalid.');
        }
        foreach ($records as $record) {
            $path = is_array($record) ? ($record['path'] ?? null) : null;
            if (! is_string($path) || $path === '' || str_contains($path, '\\') || str_starts_with($path, '/')
                || array_intersect(explode('/', $path), ['', '.', '..']) !== []
                || ! is_bool($record['existed'] ?? null)
                || (! is_int($record['permissions'] ?? null) && ($record['permissions'] ?? null) !== null)) {
                throw new RuntimeException('The pending update activation state is invalid.');
            }
        }

        return [
            ...$state,
            'application_root' => $applicationRoot,
            'operation_directory' => $operationDirectory,
            'rollback_directory' => $rollbackDirectory,
            'rollback_records' => array_values($records),
        ];
    }

    private function cleanDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        if (! @rmdir($directory)) {
            throw new RuntimeException('The completed update staging material could not be removed.');
        }
    }
}
