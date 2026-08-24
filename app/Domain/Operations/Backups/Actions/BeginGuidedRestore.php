<?php

namespace App\Domain\Operations\Backups\Actions;

use App\Domain\Operations\Backups\GuidedRestoreStateStore;
use App\Domain\Operations\Backups\Models\BackupRun;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

final readonly class BeginGuidedRestore
{
    public function __construct(
        private RestoreBackup $restore,
        private CreateBackup $backups,
        private GuidedRestoreStateStore $state,
    ) {}

    public function handle(BackupRun $target, string $confirmation, ?string $passphrase = null): BackupRun
    {
        if (($this->state->read()['status'] ?? null) === 'waiting_for_safety_backup') {
            throw new RuntimeException('A guided restore is already waiting for its safety backup.');
        }
        $this->restore->assertRestorableRun($target, $confirmation, $passphrase);
        $safety = $this->backups->start('pre-restore');
        $this->state->write([
            'status' => 'waiting_for_safety_backup',
            'target_backup_id' => $target->id,
            'safety_backup_id' => $safety->id,
            'encrypted_passphrase' => is_string($passphrase) && $passphrase !== '' ? Crypt::encryptString($passphrase) : null,
            'started_at' => now('UTC')->toIso8601String(),
            'message' => 'The restore target is verified and waiting for its bounded safety backup.',
        ]);

        return $safety;
    }
}
