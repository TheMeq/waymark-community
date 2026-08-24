<?php

namespace App\Domain\Operations\Backups;

use App\Domain\Operations\Backups\Contracts\BackupCapacityProbe;

final class NativeBackupCapacityProbe implements BackupCapacityProbe
{
    public function availableBytes(): ?int
    {
        $available = @disk_free_space(storage_path('framework'));

        return is_int($available) || is_float($available) ? (int) $available : null;
    }
}
