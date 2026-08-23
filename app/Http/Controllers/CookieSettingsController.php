<?php

namespace App\Http\Controllers;

use App\Domain\Communication\Models\PolicyConsent;
use App\Domain\Communication\Models\PolicyPage;
use App\Domain\Communication\Models\PolicyVersion;
use App\Domain\Operations\Analytics\ConsentPreferences;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class CookieSettingsController
{
    public function edit(Request $request, ConsentPreferences $preferences): View
    {
        $profile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;

        return view('privacy.cookie-settings', [
            'preferences' => $preferences->forRequest($request),
            'site' => ['name' => $profile->group_name ?? 'Waymark Community', 'strapline' => 'A local walking community'],
            'theme' => BrandTheme::fromSiteProfile($profile),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate(['analytics' => ['required', 'boolean']]);
        $analytics = $request->boolean('analytics');
        $policyVersion = $this->currentCookiePolicyVersion();

        if ($request->user() !== null) {
            $this->recordAuthenticatedChoice($request, $policyVersion, $analytics);
        }

        $payload = json_encode([
            'essential' => true,
            'analytics' => $analytics,
            'version' => ConsentPreferences::FORMAT_VERSION,
            'policy_version_id' => $policyVersion?->id,
            'updated_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR);

        return back()->with('status', 'Cookie preferences saved.')->cookie(
            'waymark_consent', $payload, 525600, '/', null,
            (bool) config('session.secure_cookie'), true, false, 'lax'
        );
    }

    private function currentCookiePolicyVersion(): ?PolicyVersion
    {
        return PolicyPage::query()->where('policy_key', 'cookies')->with('currentVersion')->first()?->currentVersion;
    }

    private function recordAuthenticatedChoice(Request $request, ?PolicyVersion $policyVersion, bool $analytics): void
    {
        DB::transaction(function () use ($request, $policyVersion, $analytics): void {
            $active = PolicyConsent::query()->where('user_id', $request->user()->id)->where('action', 'analytics')->whereNull('withdrawn_at');

            if (! $analytics) {
                $active->update(['withdrawn_at' => now()]);

                return;
            }

            if ($policyVersion === null || $policyVersion->publication_state !== 'published') {
                return;
            }

            $active->where('policy_version_id', '!=', $policyVersion->id)->update(['withdrawn_at' => now()]);
            PolicyConsent::query()->updateOrCreate(
                ['user_id' => $request->user()->id, 'policy_version_id' => $policyVersion->id, 'action' => 'analytics'],
                ['accepted_at' => now(), 'withdrawn_at' => null],
            );
        });
    }
}
