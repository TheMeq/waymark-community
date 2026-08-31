<?php

namespace App\Domain\Operations\Health;

interface OpcodeCacheProbe
{
    public function enabled(): bool;
}
