<?php

namespace App\Domain\Events\Enums;

enum EventType: string
{
    case Walk = 'walk';
    case Social = 'social';
    case Holiday = 'holiday';
}
