<?php

namespace App\Domain\Operations\Backups\Contracts;

interface BackupCapacityProbe
{
    public function availableBytes(): ?int;
}
