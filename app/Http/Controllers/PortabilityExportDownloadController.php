<?php

namespace App\Http\Controllers;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Operations\Portability\Models\PortabilityExportRun;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PortabilityExportDownloadController
{
    public function __invoke(PortabilityExportRun $export): StreamedResponse
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->hasCapability(ModuleCapability::ManageAccounts), 403);
        abort_unless(
            $export->status === 'completed'
            && $export->storage_disk === 'local'
            && is_string($export->storage_path)
            && preg_match('#\Aportability-exports/[A-Za-z0-9._-]+\.zip\z#D', $export->storage_path) === 1,
            404,
        );

        return Storage::disk('local')->download($export->storage_path, basename($export->storage_path));
    }
}
