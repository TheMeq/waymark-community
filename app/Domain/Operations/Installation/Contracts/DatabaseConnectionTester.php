<?php

namespace App\Domain\Operations\Installation\Contracts;

use App\Domain\Operations\Installation\DatabaseConfiguration;
use App\Domain\Operations\Installation\DatabaseConnectionResult;

interface DatabaseConnectionTester
{
    public function test(DatabaseConfiguration $configuration): DatabaseConnectionResult;
}
