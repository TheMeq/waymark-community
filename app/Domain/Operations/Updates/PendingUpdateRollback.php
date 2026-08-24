<?php

namespace App\Domain\Operations\Updates;

use RuntimeException;

final class PendingUpdateRollback extends RuntimeException
{
    public function __construct(public readonly string $rollbackToken)
    {
        parent::__construct('The new release failed activation. Continue in a fresh old runtime to verify database rollback.');
    }
}
