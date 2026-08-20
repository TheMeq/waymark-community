<?php

namespace App\Domain\Membership\Enums;

enum MembershipStatus: string
{
    case Unverified = 'unverified';
    case Verified = 'verified';
    case Lapsed = 'lapsed';
    case NotMember = 'not_member';
}
