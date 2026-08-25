<?php

namespace App\Domain\Operations\Installation;

enum DatabaseInstallationState: string
{
    case Empty = 'empty';
    case Complete = 'complete';
    case Incomplete = 'incomplete';
    case Ambiguous = 'ambiguous';
}
