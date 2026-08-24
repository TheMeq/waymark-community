<?php

namespace App\Http\Controllers;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Operations\Backups\Actions\CreateBackup;
use App\Domain\Operations\Backups\Actions\PruneBackups;
use App\Domain\Operations\Backups\Models\BackupRun;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

final readonly class BackupWorkController
{
    public function advance(BackupRun $backup, CreateBackup $backups, PruneBackups $retention): RedirectResponse
    {
        $this->authorise();
        abort_unless(in_array($backup->status, ['queued', 'running'], true), 404);

        $backups->advance($backup);
        $retention->handle();

        return redirect('/admin/system-health')->with('status', 'Backup work advanced.');
    }

    public function retry(BackupRun $backup, CreateBackup $backups): RedirectResponse
    {
        $this->authorise();
        $backups->retry($backup);

        return redirect('/admin/system-health')->with('status', 'Backup queued for retry.');
    }

    private function authorise(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->hasCapability(ModuleCapability::ManageAccounts), 403);
    }
}
