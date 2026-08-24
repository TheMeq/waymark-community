<?php

namespace App\Domain\Operations\Scheduling\Contracts;

use App\Domain\Operations\Scheduling\FallbackRunResult;
use Illuminate\Support\Carbon;

interface FallbackRunner
{
    public function handle(string $trigger, ?Carbon $now = null): FallbackRunResult;
}
