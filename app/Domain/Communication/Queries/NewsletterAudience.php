<?php

namespace App\Domain\Communication\Queries;

use App\Domain\Communication\Models\Newsletter;
use App\Models\User;
use Illuminate\Support\Collection;

final class NewsletterAudience
{
    public function for(Newsletter $newsletter): Collection
    {
        return User::query()->whereIn('role', $newsletter->audience_roles)->whereIn('account_status', $newsletter->audience_account_statuses)
            ->whereHas('communicationPreferences', fn ($query) => $query->where('category', 'group_news')->where('is_subscribed', true))
            ->when($newsletter->consent_required, fn ($query) => $query->whereHas('policyConsents', fn ($consents) => $consents->where('policy_version_id', $newsletter->consent_policy_version_id)->where('action', 'newsletter')->whereNull('withdrawn_at')))
            ->orderBy('id')->get();
    }
}
