<?php

namespace App\Domain\Operations\Analytics;

use App\Domain\Operations\Models\AnalyticsSetting;
use Illuminate\Http\Request;

final readonly class ExternalAnalytics
{
    public function __construct(private ConsentPreferences $consent) {}

    public function forRequest(Request $request): ?AnalyticsViewModel
    {
        if (! $this->consent->analyticsAllowed($request)) {
            return null;
        }

        $setting = AnalyticsSetting::query()->where('singleton_key', 'public')->where('enabled', true)->first();
        if ($setting === null || ! in_array($setting->provider, ['ga4', 'plausible'], true) || blank($setting->tracking_id)) {
            return null;
        }

        return new AnalyticsViewModel($setting->provider, $setting->tracking_id);
    }
}
