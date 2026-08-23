<?php

namespace App\Domain\Operations\Analytics;

use Illuminate\Http\Request;

final class ConsentPreferences
{
    public const FORMAT_VERSION = 1;

    public function analyticsAllowed(Request $request): bool
    {
        return $this->forRequest($request)['analytics'];
    }

    /** @return array{decided: bool, essential: true, analytics: bool, version: int} */
    public function forRequest(Request $request): array
    {
        $preferences = json_decode((string) $request->cookie('waymark_consent'), true);

        $valid = is_array($preferences)
            && ($preferences['essential'] ?? false) === true
            && ($preferences['version'] ?? null) === self::FORMAT_VERSION
            && is_bool($preferences['analytics'] ?? null);

        return [
            'decided' => $valid,
            'essential' => true,
            'analytics' => $valid && $preferences['analytics'] === true,
            'version' => self::FORMAT_VERSION,
        ];
    }
}
