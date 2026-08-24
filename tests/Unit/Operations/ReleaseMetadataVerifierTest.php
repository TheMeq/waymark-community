<?php

namespace Tests\Unit\Operations;

use App\Domain\Operations\Updates\ReleaseMetadataVerifier;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\UpdateSigningFixture;

final class ReleaseMetadataVerifierTest extends TestCase
{
    public function test_valid_signed_stable_release_metadata_is_accepted(): void
    {
        $privateKey = UpdateSigningFixture::privateKey();
        $feed = $this->signedFeed($this->payload(), $privateKey);

        $metadata = (new ReleaseMetadataVerifier)->verify($feed, UpdateSigningFixture::publicKeyBase64());

        $this->assertSame('1.2.0', $metadata->version);
        $this->assertTrue($metadata->securityRelease);
        $this->assertSame(str_repeat('a', 64), $metadata->packageSha256);
    }

    public function test_tampered_beta_or_prerelease_metadata_is_rejected(): void
    {
        $privateKey = UpdateSigningFixture::privateKey();
        $feed = $this->signedFeed($this->payload(), $privateKey);
        $feed['payload']['summary'] = 'Tampered after signing';
        $this->expectException(RuntimeException::class);
        (new ReleaseMetadataVerifier)->verify($feed, UpdateSigningFixture::publicKeyBase64());
    }

    public function test_non_stable_channel_is_rejected_even_with_a_valid_signature(): void
    {
        $privateKey = UpdateSigningFixture::privateKey();
        $payload = $this->payload();
        $payload['channel'] = 'beta';

        $this->expectException(RuntimeException::class);
        (new ReleaseMetadataVerifier)->verify($this->signedFeed($payload, $privateKey), UpdateSigningFixture::publicKeyBase64());
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function signedFeed(array $payload, \OpenSSLAsymmetricKey $privateKey): array
    {
        openssl_sign(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return ['payload' => $payload, 'signature' => base64_encode($signature)];
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'schema' => 1,
            'channel' => 'stable',
            'version' => '1.2.0',
            'published_at' => '2026-08-24T09:00:00Z',
            'security_release' => true,
            'summary' => 'Security and reliability improvements.',
            'release_notes' => ['Hardened update verification.', 'Improved shared-host recovery.'],
            'package_url' => 'https://updates.example.test/waymark-community-1.2.0.zip',
            'package_sha256' => str_repeat('a', 64),
            'package_size_bytes' => 12500000,
            'requirements' => [
                'php' => '8.3.0',
                'extensions' => ['ctype', 'openssl', 'zip'],
                'database' => ['mysql' => '8.0.0', 'mariadb' => '10.6.0'],
                'disk_free_bytes' => 50000000,
            ],
        ];
    }
}
