<?php

namespace App\Domain\Operations\Analytics;

use Illuminate\Http\Request;

final class ConsentPreferences
{
    public function analyticsAllowed(Request $request): bool
    {
        $preferences = json_decode((string) $request->cookie('waymark_consent'), true);

        return is_array($preferences)
            && ($preferences['essential'] ?? false) === true
            && ($preferences['analytics'] ?? false) === true;
    }
}
