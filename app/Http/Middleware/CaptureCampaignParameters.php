<?php

namespace App\Http\Middleware;

use App\Domain\Operations\Analytics\CampaignParameterFilter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class CaptureCampaignParameters
{
    public function __construct(private CampaignParameterFilter $campaignParameters) {}

    public function handle(Request $request, Closure $next): Response
    {
        $campaign = $this->campaignParameters->from($request->query());

        if ($campaign !== []) {
            $request->session()->put('waymark.campaign', $campaign);
        }

        return $next($request);
    }
}
