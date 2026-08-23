<?php

namespace App\Domain\Operations\Environment;

final class StagingMode
{
    public function active(): bool
    {
        return (bool) config('waymark.staging', app()->environment('staging'));
    }
}
