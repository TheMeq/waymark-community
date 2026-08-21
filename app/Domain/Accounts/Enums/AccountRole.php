<?php

namespace App\Domain\Accounts\Enums;

enum AccountRole: string
{
    case RegisteredUser = 'registered_user';
    case VerifiedMember = 'verified_member';
    case WalkLeader = 'walk_leader';
    case Moderator = 'moderator';
    case Administrator = 'administrator';
}
