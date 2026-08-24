<?php

namespace Tests\Feature\Operations;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Operations\Updates\Actions\CheckForUpdates;
use App\Domain\Operations\Updates\Contracts\UpdateEnvironmentProbe;
use App\Domain\Operations\Updates\UpdateEnvironment;
use App\Domain\Operations\Updates\UpdateStateStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;
use Tests\Support\UpdateSigningFixture;
use Tests\TestCase;

final class UpdateCheckTest extends TestCase
{
    use RefreshDatabase;

    private string $statePath;

    private \OpenSSLAsymmetricKey $privateKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->statePath = storage_path('framework/testing/update-state-'.bin2hex(random_bytes(8)).'.json');
        $this->privateKey = UpdateSigningFixture::privateKey();
        config()->set('waymark.version', '1.0.0');
        config()->set('waymark.updates.metadata_url', 'https://updates.example.test/stable.json');
        config()->set('waymark.updates.public_key_base64', UpdateSigningFixture::publicKeyBase64());
        config()->set('waymark.updates.state_path', $this->statePath);
        $this->app->instance(UpdateEnvironmentProbe::class, new class implements UpdateEnvironmentProbe
        {
            public function capture(): UpdateEnvironment
            {
                return new UpdateEnvironment('8.3.12', ['ctype', 'openssl', 'zip'], 'mysql', '8.4.2', 100000000);
            }
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->statePath);
        parent::tearDown();
    }

    public function test_verified_security_release_is_stored_and_shown_without_auto_install_controls(): void
    {
        Http::fake(['https://updates.example.test/stable.json' => Http::response($this->signedFeed(), 200)]);

        $result = app(CheckForUpdates::class)->handle('manual');

        $this->assertTrue($result->updateAvailable);
        $this->assertTrue($result->metadata->securityRelease);
        $this->assertTrue($result->compatibility->compatible());
        $state = app(UpdateStateStore::class)->read();
        $this->assertSame('1.2.0', $state['metadata']['version']);

        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $this->actingAs($administrator)->get('/admin/update-centre')
            ->assertSuccessful()
            ->assertSee('System health needs attention')
            ->assertSee('Security release')
            ->assertSee('Hardened update verification.')
            ->assertDontSee('Install automatically');
    }

    public function test_invalid_signature_records_generic_failure_without_accepting_metadata(): void
    {
        $feed = $this->signedFeed();
        $feed['payload']['version'] = '9.9.9';
        Http::fake(['*' => Http::response($feed, 200)]);

        $this->expectException(\RuntimeException::class);
        try {
            app(CheckForUpdates::class)->handle('manual');
        } finally {
            $state = app(UpdateStateStore::class)->read();
            $this->assertSame('failed', $state['status']);
            $this->assertArrayNotHasKey('metadata', $state);
        }
    }

    public function test_update_check_has_manual_command_and_daily_passive_schedule(): void
    {
        Http::fake(['*' => Http::response($this->signedFeed(), 200)]);
        $this->assertSame(0, Artisan::call('waymark:check-for-updates'));
        $this->assertStringContainsString('1.2.0', Artisan::output());

        $event = collect(Schedule::events())->first(fn ($event): bool => str_contains((string) $event->command, 'waymark:check-for-updates'));
        $this->assertNotNull($event);
        $this->assertSame('0 7 * * *', $event->expression);
    }

    /** @return array<string, mixed> */
    private function signedFeed(): array
    {
        $payload = [
            'schema' => 1, 'channel' => 'stable', 'version' => '1.2.0', 'published_at' => '2026-08-24T09:00:00Z',
            'security_release' => true, 'summary' => 'Security and reliability improvements.',
            'release_notes' => ['Hardened update verification.', 'Improved shared-host recovery.'],
            'package_url' => 'https://updates.example.test/waymark-community-1.2.0.zip',
            'package_sha256' => str_repeat('a', 64), 'package_size_bytes' => 12500000,
            'requirements' => ['php' => '8.3.0', 'extensions' => ['ctype', 'openssl', 'zip'], 'database' => ['mysql' => '8.0.0', 'mariadb' => '10.6.0'], 'disk_free_bytes' => 50000000],
        ];
        openssl_sign(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        return ['payload' => $payload, 'signature' => base64_encode($signature)];
    }
}
