<?php

namespace App\Domain\Operations\Installation;

enum InstallationAttemptStatus: string
{
    case Running = 'running';
    case Failed = 'failed';
    case Completed = 'completed';
}
