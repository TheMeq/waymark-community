<?php

namespace App\Domain\Gallery;

enum PhotoUploadPolicyDecision: string
{
    case EmailVerificationRequired = 'email_verification_required';
    case PolicyAcceptanceRequired = 'policy_acceptance_required';
    case PolicyVersionAcceptanceRequired = 'policy_version_acceptance_required';
    case UploadAllowedWithReminder = 'upload_allowed_with_reminder';
}
