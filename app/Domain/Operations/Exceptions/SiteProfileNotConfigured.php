<?php

namespace App\Domain\Operations\Exceptions;

use RuntimeException;

final class SiteProfileNotConfigured extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The installation site profile has not been configured.');
    }
}
