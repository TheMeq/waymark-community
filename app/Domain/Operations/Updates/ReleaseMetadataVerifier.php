<?php

namespace App\Domain\Operations\Updates;

use RuntimeException;

final class ReleaseMetadataVerifier
{
    /** @param array<string, mixed> $feed */
    public function verify(array $feed, string $publicKeyBase64): ReleaseMetadata
    {
        $payload = $feed['payload'] ?? null;
        $signature = is_string($feed['signature'] ?? null) ? base64_decode($feed['signature'], true) : false;
        $publicKeyPem = base64_decode($publicKeyBase64, true);
        $encodedPayload = is_array($payload)
            ? json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : false;
        $publicKey = is_string($publicKeyPem) ? openssl_pkey_get_public($publicKeyPem) : false;

        if (! is_string($encodedPayload) || ! is_string($signature) || ! $publicKey instanceof \OpenSSLAsymmetricKey
            || openssl_verify($encodedPayload, $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw $this->failure();
        }

        if (($payload['schema'] ?? null) !== 1 || ($payload['channel'] ?? null) !== 'stable'
            || ! is_string($payload['version'] ?? null) || ! preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/', $payload['version'])
            || ! is_string($payload['published_at'] ?? null) || strtotime($payload['published_at']) === false
            || ! is_bool($payload['security_release'] ?? null)
            || ! is_string($payload['summary'] ?? null) || trim($payload['summary']) === '' || strlen($payload['summary']) > 1000
            || ! $this->validNotes($payload['release_notes'] ?? null)
            || ! $this->httpsUrl($payload['package_url'] ?? null)
            || ! is_string($payload['package_sha256'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/', $payload['package_sha256'])
            || ! is_int($payload['package_size_bytes'] ?? null) || $payload['package_size_bytes'] < 1
            || ! $this->validRequirements($payload['requirements'] ?? null)) {
            throw $this->failure();
        }

        return new ReleaseMetadata(
            version: $payload['version'],
            publishedAt: $payload['published_at'],
            securityRelease: $payload['security_release'],
            summary: $payload['summary'],
            releaseNotes: $payload['release_notes'],
            packageUrl: $payload['package_url'],
            packageSha256: $payload['package_sha256'],
            packageSizeBytes: $payload['package_size_bytes'],
            requirements: $payload['requirements'],
        );
    }

    private function validNotes(mixed $notes): bool
    {
        if (! is_array($notes) || ! array_is_list($notes) || count($notes) > 50) {
            return false;
        }
        foreach ($notes as $note) {
            if (! is_string($note) || trim($note) === '' || strlen($note) > 2000) {
                return false;
            }
        }

        return true;
    }

    private function validRequirements(mixed $requirements): bool
    {
        if (! is_array($requirements) || ! is_string($requirements['php'] ?? null)
            || ! preg_match('/^\d+\.\d+\.\d+$/', $requirements['php'])
            || ! is_array($requirements['extensions'] ?? null) || ! array_is_list($requirements['extensions'])
            || ! is_array($requirements['database'] ?? null)
            || ! is_string($requirements['database']['mysql'] ?? null)
            || ! is_string($requirements['database']['mariadb'] ?? null)
            || ! is_int($requirements['disk_free_bytes'] ?? null) || $requirements['disk_free_bytes'] < 1) {
            return false;
        }

        foreach ($requirements['extensions'] as $extension) {
            if (! is_string($extension) || preg_match('/^[a-z0-9_]+$/', $extension) !== 1) {
                return false;
            }
        }

        return preg_match('/^\d+\.\d+\.\d+$/', $requirements['database']['mysql']) === 1
            && preg_match('/^\d+\.\d+\.\d+$/', $requirements['database']['mariadb']) === 1;
    }

    private function httpsUrl(mixed $url): bool
    {
        return is_string($url) && filter_var($url, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
    }

    private function failure(): RuntimeException
    {
        return new RuntimeException('Stable release metadata verification failed. No update information was accepted.');
    }
}
