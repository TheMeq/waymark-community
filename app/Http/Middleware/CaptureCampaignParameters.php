<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CaptureCampaignParameters
{
    private const ALLOWED = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    public function handle(Request $request, Closure $next): Response
    {
        $campaign = [];

        foreach (self::ALLOWED as $key) {
            $value = $request->query($key);
            if (! is_string($value)) {
                continue;
            }

            $value = trim($value);
            if ($value !== '' && mb_strlen($value) <= 100 && preg_match('/\A[\pL\pN _.\-~:@+\/]+\z/u', $value) === 1) {
                $campaign[$key] = $value;
            }
        }

        if ($campaign !== []) {
            $request->session()->put('waymark.campaign', $campaign);
        }

        return $next($request);
    }
}
