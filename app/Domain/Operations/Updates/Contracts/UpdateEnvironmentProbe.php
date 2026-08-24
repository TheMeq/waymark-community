<?php

namespace App\Domain\Operations\Updates\Contracts;

use App\Domain\Operations\Updates\UpdateEnvironment;

interface UpdateEnvironmentProbe
{
    public function capture(): UpdateEnvironment;
}
