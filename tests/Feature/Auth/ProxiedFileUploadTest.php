<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Features\SupportFileUploads\GenerateSignedUploadUrl;
use Tests\TestCase;

final class ProxiedFileUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('tmp-for-tests');
        URL::forceRootUrl('https://demo.example.test');
        URL::forceScheme('https');
    }

    public function test_https_signed_workbook_upload_survives_http_hop_from_local_reverse_proxy(): void
    {
        $response = $this->upload($this->signedUrl());
        $response->assertOk()->assertJsonStructure(['paths']);
        $this->assertCount(1, $response->json('paths'));
    }

    public function test_forwarded_headers_from_an_untrusted_client_cannot_validate_a_different_scheme(): void
    {
        $this->upload($this->signedUrl(), '203.0.113.24')->assertUnauthorized();
    }

    public function test_local_proxy_does_not_bypass_tampered_upload_signatures(): void
    {
        $url = preg_replace('/signature=[^&]+/', 'signature=invalid', $this->signedUrl());
        $this->upload($url)->assertUnauthorized();
    }

    public function test_an_explicit_remote_proxy_address_can_be_trusted_without_trusting_other_clients(): void
    {
        config()->set('trustedproxy.proxies', '192.0.2.10');
        $url = $this->signedUrl();
        $this->upload($url, '192.0.2.10')->assertOk();
        $this->upload($url, '192.0.2.11')->assertUnauthorized();
    }

    public function test_local_proxy_does_not_bypass_expired_upload_signatures(): void
    {
        $url = $this->signedUrl();
        $this->travel(6)->minutes();
        $this->upload($url)->assertUnauthorized();
    }

    private function signedUrl(): string
    {
        return app(GenerateSignedUploadUrl::class)->forLocal();
    }

    private function upload(string $signedUrl, string $proxyAddress = '127.0.0.1')
    {
        $url = str_replace('https://', 'http://', $signedUrl);

        return $this->withServerVariables([
            'REMOTE_ADDR' => $proxyAddress, 'HTTPS' => 'off', 'SERVER_PORT' => 80,
        ])->withHeaders([
            'X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443',
        ])->postJson($url, ['files' => [UploadedFile::fake()->createWithContent('programme.xlsx', 'workbook transport payload')]]);
    }
}
