<?php

namespace App\Domain\Events\Enums;

enum EventStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Published = 'published';
    case Changed = 'changed';
    case Postponed = 'postponed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case Archived = 'archived';
}
