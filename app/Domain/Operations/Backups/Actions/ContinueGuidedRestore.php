<?php

namespace App\Domain\Operations\Backups\Actions;

use App\Domain\Operations\Backups\GuidedRestoreStateStore;
use App\Domain\Operations\Backups\Models\BackupRun;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

final readonly class ContinueGuidedRestore
{
    public function __construct(
        private CreateBackup $backups,
        private RestoreBackup $restore,
        private GuidedRestoreStateStore $state,
    ) {}

    public function handle(): bool
    {
        $state = $this->state->read();
        if (! is_array($state) || ($state['status'] ?? null) !== 'waiting_for_safety_backup'
            || ! is_int($state['target_backup_id'] ?? null) || ! is_int($state['safety_backup_id'] ?? null)) {
            throw new RuntimeException('No guided restore is waiting for a safety backup.');
        }
        $target = BackupRun::query()->where('status', 'completed')->findOrFail($state['target_backup_id']);
        $safety = BackupRun::query()->findOrFail($state['safety_backup_id']);
        if (in_array($safety->status, ['queued', 'running'], true)) {
            $safety = $this->backups->advance($safety);
        }
        if (in_array($safety->status, ['queued', 'running'], true)) {
            return false;
        }
        if ($safety->status !== 'completed') {
            throw new RuntimeException('The guided-restore safety backup failed. Retry it before continuing.');
        }
        $encrypted = $state['encrypted_passphrase'] ?? null;
        $passphrase = is_string($encrypted) ? Crypt::decryptString($encrypted) : null;
        $this->restore->fromRunWithSafetyBackup($target, $safety, RestoreBackup::CONFIRMATION, $passphrase);
        $this->state->clear();

        return true;
    }
}
