<?php

namespace App\Http\Controllers;

use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

final class BrandingPreviewController
{
    public function __invoke(Request $request): View
    {
        $actor = $request->user();
        $payload = Cache::get('branding-preview:'.(string) $request->query('token'));
        abort_unless($actor instanceof User && is_array($payload) && $payload['user_id'] === $actor->id, 404);

        $profile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;
        $profile->fill($payload['values']);
        $viewport = in_array($request->query('viewport'), ['desktop', 'tablet', 'mobile'], true) ? $request->query('viewport') : 'desktop';

        return view('admin.branding-preview', ['theme' => BrandTheme::fromSiteProfile($profile), 'viewport' => $viewport, 'site' => ['name' => $profile->group_name ?? 'Waymark Community', 'strapline' => 'A local walking community']]);
    }
}
