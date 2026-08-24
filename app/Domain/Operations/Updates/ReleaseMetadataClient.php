<?php

namespace App\Domain\Operations\Updates;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class ReleaseMetadataClient
{
    public function __construct(private ReleaseMetadataVerifier $verifier) {}

    public function fetch(): ReleaseMetadata
    {
        $url = trim((string) config('waymark.updates.metadata_url'));
        $publicKey = trim((string) config('waymark.updates.public_key_base64'));
        if (! str_starts_with(strtolower($url), 'https://') || $publicKey === '') {
            throw new RuntimeException('Stable release checks are not configured securely.');
        }

        $response = Http::acceptJson()->timeout(10)->withOptions(['allow_redirects' => false])->get($url);
        $feed = $response->successful() ? $response->json() : null;
        if (! is_array($feed)) {
            throw new RuntimeException('Stable release metadata could not be retrieved.');
        }

        return $this->verifier->verify($feed, $publicKey);
    }
}
