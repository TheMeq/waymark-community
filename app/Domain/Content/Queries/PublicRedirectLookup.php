<?php

namespace App\Domain\Content\Queries;

use App\Domain\Content\Models\PublicRedirect;
use App\Domain\Content\Support\PublicContentCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

final class PublicRedirectLookup
{
    /** @return array{target_url:string,status_code:int}|null */
    public function find(string $sourcePath): ?array
    {
        $redirects = Cache::remember(
            PublicContentCache::REDIRECTS,
            now()->addSeconds(max(1, (int) config('waymark.public_cache_seconds', 300))),
            function (): array {
                if (! Schema::hasTable('public_redirects')) {
                    return [];
                }

                return PublicRedirect::query()
                    ->where('enabled', true)
                    ->get(['source_path', 'target_url', 'status_code'])
                    ->mapWithKeys(fn (PublicRedirect $redirect): array => [
                        $redirect->source_path => [
                            'target_url' => $redirect->target_url,
                            'status_code' => $redirect->status_code,
                        ],
                    ])
                    ->all();
            },
        );

        $redirect = $redirects[$sourcePath] ?? null;

        return is_array($redirect) ? $redirect : null;
    }
}
