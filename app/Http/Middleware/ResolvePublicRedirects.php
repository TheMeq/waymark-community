<?php

namespace App\Http\Middleware;

use App\Domain\Content\Models\PublicRedirect;
use App\Domain\Operations\Analytics\CampaignParameterFilter;
use App\Domain\Operations\Installation\InstallationState;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

final readonly class ResolvePublicRedirects
{
    public function __construct(
        private CampaignParameterFilter $campaignParameters,
        private InstallationState $installation,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->installation->installationRequired() || $request->is('recovery')) {
            return $next($request);
        }

        if ($request->isMethodSafe() && Schema::hasTable('public_redirects')) {
            $path = '/'.ltrim($request->path(), '/');
            $redirect = PublicRedirect::query()->where('enabled', true)->where('source_path', $path)->first();
            if ($redirect instanceof PublicRedirect) {
                $target = $redirect->target_url;

                if (str_starts_with($target, '/') && ! str_starts_with($target, '//')) {
                    $campaign = $this->campaignParameters->from($request->query());
                    if ($campaign !== []) {
                        $target .= '?'.http_build_query($campaign, '', '&', PHP_QUERY_RFC3986);
                    }
                }

                return redirect()->to($target, $redirect->status_code);
            }
        }

        return $next($request);
    }
}
