<?php

namespace App\Domain\Accounts\Enums;

enum ModuleCapability: string
{
    case AccessAdministration = 'admin.access';
    case ManagePermissions = 'admin.manage_permissions';
    case CreateWalks = 'walks.create';
    case ManageOwnWalks = 'walks.manage_own';
    case ManageAllWalks = 'walks.manage_all';
    case ManageSocials = 'socials.manage';
    case ManageHolidays = 'holidays.manage';
    case ManageOwnEventUpdates = 'event_updates.manage_own';
    case ManageAllEventUpdates = 'event_updates.manage_all';
    case ManageEventConfiguration = 'event_configuration.manage';
    case ManageMembershipVerification = 'accounts.manage_membership_verification';
    case ManageAccounts = 'accounts.manage';
    case ModerateOwnEventPhotos = 'gallery.moderate_own_event_photos';
    case ModerateAllCommunityPhotos = 'gallery.moderate_all_community_photos';
}
