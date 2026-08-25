<?php

namespace App\Domain\Operations\Installation\Contracts;

interface PublicApplicationExposureProbe
{
    public function protected(string $baseUrl): ?bool;
}
