<?php

namespace App\Domain\Operations\Backups\Contracts;

interface RestoreHealthProbe
{
    public function assertHealthy(): void;
}
