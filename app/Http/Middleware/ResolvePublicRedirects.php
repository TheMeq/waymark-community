<?php

namespace App\Http\Middleware;

use App\Domain\Content\Presentation\PublicUrl;
use App\Domain\Content\Queries\PublicRedirectLookup;
use App\Domain\Operations\Analytics\CampaignParameterFilter;
use App\Domain\Operations\Installation\InstallationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ResolvePublicRedirects
{
    public function __construct(
        private CampaignParameterFilter $campaignParameters,
        private InstallationState $installation,
        private PublicRedirectLookup $redirects,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->installation->installationRequired() || $request->is('recovery')) {
            return $next($request);
        }

        if ($request->isMethodSafe()) {
            $path = '/'.ltrim($request->path(), '/');
            $redirect = $this->redirects->find($path);
            if ($redirect !== null) {
                $target = $redirect['target_url'];

                if (str_starts_with($target, '/') && ! str_starts_with($target, '//')) {
                    $campaign = $this->campaignParameters->from($request->query());
                    $target = PublicUrl::resolve($target) ?? $target;
                    if ($campaign !== []) {
                        $target .= '?'.http_build_query($campaign, '', '&', PHP_QUERY_RFC3986);
                    }
                }

                return redirect()->to($target, $redirect['status_code']);
            }
        }

        return $next($request);
    }
}
