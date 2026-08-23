<?php

namespace App\Http\Responses;

use App\Domain\Content\Queries\KnownUnavailableContent;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final readonly class PublicNotFoundResponse
{
    public function __construct(private KnownUnavailableContent $knownContent) {}

    public function make(Request $request): Response
    {
        $profile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;
        $content = $this->knownContent->find($request->path());

        return response()->view($content === null ? 'errors.404' : 'errors.unavailable', [
            'content' => $content,
            'site' => ['name' => $profile->group_name ?? 'Waymark Community', 'strapline' => 'A local walking community'],
            'theme' => BrandTheme::fromSiteProfile($profile),
        ], 404);
    }
}
