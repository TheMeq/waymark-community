<?php

namespace App\Http\Controllers;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Operations\Backups\Models\BackupRun;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class BackupDownloadController
{
    public function __invoke(BackupRun $backup): StreamedResponse
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->hasCapability(ModuleCapability::ManageAccounts), 403);
        abort_unless($backup->status === 'completed' && is_string($backup->storage_disk) && is_string($backup->storage_path), 404);

        return Storage::disk($backup->storage_disk)->download($backup->storage_path, basename($backup->storage_path));
    }
}
