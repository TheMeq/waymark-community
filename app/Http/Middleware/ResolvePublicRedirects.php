<?php

namespace App\Http\Middleware;

use App\Domain\Content\Models\PublicRedirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

final class ResolvePublicRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() && Schema::hasTable('public_redirects')) {
            $path = '/'.ltrim($request->path(), '/');
            $redirect = PublicRedirect::query()->where('enabled', true)->where('source_path', $path)->first();
            if ($redirect instanceof PublicRedirect) {
                return redirect()->to($redirect->target_url, $redirect->status_code);
            }
        }

        return $next($request);
    }
}
