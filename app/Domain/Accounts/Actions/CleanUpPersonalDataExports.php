<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Models\PersonalDataExport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class CleanUpPersonalDataExports
{
    public function handle(int $limit = 25): void
    {
        PersonalDataExport::query()
            ->whereIn('status', ['revoked', 'expired', 'failed'])
            ->whereNotNull('storage_path')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->each(fn (int $exportId) => $this->handleExport($exportId));
    }

    public function handleExport(int $exportId): void
    {
        $path = $this->pendingPath($exportId);
        if ($path === null) {
            return;
        }

        try {
            $disk = Storage::disk('local');
            if (! $disk->exists($path) || $disk->delete($path)) {
                $this->clearPathAfterDeletion($exportId, $path);
            }
        } catch (Throwable) {
            // The revoked record retains its safe path for a later bounded cleanup run.
        }
    }

    private function pendingPath(int $exportId): ?string
    {
        $ownerId = PersonalDataExport::query()->whereKey($exportId)->value('user_id');
        if (! is_int($ownerId)) {
            return null;
        }

        return DB::transaction(function () use ($exportId, $ownerId): ?string {
            User::query()->lockForUpdate()->findOrFail($ownerId);
            $export = PersonalDataExport::query()->lockForUpdate()->findOrFail($exportId);
            if (! in_array($export->status, ['revoked', 'expired', 'failed'], true) || $export->storage_path === null) {
                return null;
            }
            if (! $export->hasSafeStoragePath()) {
                $export->update(['storage_path' => null]);

                return null;
            }

            return $export->storage_path;
        });
    }

    private function clearPathAfterDeletion(int $exportId, string $path): void
    {
        $ownerId = PersonalDataExport::query()->whereKey($exportId)->value('user_id');
        if (! is_int($ownerId)) {
            return;
        }

        DB::transaction(function () use ($exportId, $ownerId, $path): void {
            User::query()->lockForUpdate()->findOrFail($ownerId);
            $export = PersonalDataExport::query()->lockForUpdate()->findOrFail($exportId);
            if (in_array($export->status, ['revoked', 'expired', 'failed'], true) && $export->storage_path === $path) {
                $export->update(['storage_path' => null]);
            }
        });
    }
}
