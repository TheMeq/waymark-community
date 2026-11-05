<?php

namespace App\Domain\SiteMedia\Actions;

use App\Domain\Content\Presentation\PublicImageReference;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final readonly class DownloadPublicIconImage
{
    public function __construct(private ResolvePublicImageHost $hosts) {}

    public function get(string $url): ?string
    {
        if (! PublicImageReference::isAllowed($url) || ! str_starts_with($url, 'https://') || ! defined('CURLOPT_RESOLVE')) {
            return null;
        }
        $parts = parse_url($url);
        if (($parts['port'] ?? 443) !== 443) {
            return null;
        }

        try {
            return Cache::remember('waymark.app-icon-source.'.hash('sha256', $url), now()->addMinutes(10), function () use ($url, $parts): ?string {
                $ip = $this->hosts->resolve($parts['host']);
                if ($ip === null) {
                    return null;
                }
                $response = Http::connectTimeout(2)->timeout(5)->withOptions([
                    'allow_redirects' => false,
                    'proxy' => '',
                    'curl' => [CURLOPT_RESOLVE => [$parts['host'].':443:'.$ip]],
                    'progress' => static function ($total, $downloaded): void {
                        if ($total > 8 * 1024 * 1024 || $downloaded > 8 * 1024 * 1024) {
                            throw new \RuntimeException('App icon source exceeds the download limit.');
                        }
                    },
                ])->get($url);

                return $response->successful() && strlen($response->body()) <= 8 * 1024 * 1024 ? $response->body() : null;
            });
        } catch (\Throwable) {
            return null;
        }
    }
}
