<?php

namespace App\Domain\SiteMedia\Enums;

enum ManagedImageSource: string
{
    case Managed = 'managed';
    case External = 'external';
    case None = 'none';
}
