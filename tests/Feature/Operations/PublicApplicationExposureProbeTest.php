<?php

namespace Tests\Feature\Operations;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Operations\Installation\Contracts\PublicApplicationExposureProbe;
use App\Domain\Operations\Installation\NativePublicApplicationExposureProbe;
use App\Domain\Operations\Installation\SetupHealth;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class PublicApplicationExposureProbeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(interface_exists(PublicApplicationExposureProbe::class), 'The public application exposure probe contract is missing.');
        $this->assertTrue(class_exists(NativePublicApplicationExposureProbe::class), 'The native public application exposure probe is missing.');
    }

    public function test_native_probe_confirms_representative_internal_paths_are_denied_over_http(): void
    {
        Http::fake([
            'https://walks.example/application/*' => Http::response('Forbidden', 403),
        ]);

        $result = (new NativePublicApplicationExposureProbe(app(Factory::class)))->protected('https://walks.example');

        $this->assertTrue($result);
        Http::assertSentCount(3);
    }

    public function test_native_probe_reports_exposure_when_any_internal_file_is_retrievable(): void
    {
        Http::fake([
            'https://walks.example/application/.env.example*' => Http::response('APP_ENV=production', 200),
            'https://walks.example/application/*' => Http::response('Forbidden', 403),
        ]);

        $this->assertFalse((new NativePublicApplicationExposureProbe(app(Factory::class)))->protected('https://walks.example'));
    }

    public function test_public_html_setup_health_blocks_completion_when_http_protection_is_not_confirmed(): void
    {
        config()->set('waymark.deployment_layout', 'public-html');
        $this->app->instance(PublicApplicationExposureProbe::class, new class implements PublicApplicationExposureProbe
        {
            public function protected(string $baseUrl): ?bool
            {
                return false;
            }
        });

        $checks = app(SetupHealth::class)->checks('https://walks.example');
        $protection = collect($checks)->firstWhere('label', 'Internal application protection');

        $this->assertFalse($protection['passed']);
        $this->assertStringContainsString('publicly accessible', $protection['message']);
        $this->assertFalse(app(SetupHealth::class)->ready('https://walks.example'));
    }

    public function test_public_html_system_health_reports_exposed_application_as_critical(): void
    {
        config()->set('waymark.deployment_layout', 'public-html');
        $this->app->instance(PublicApplicationExposureProbe::class, new class implements PublicApplicationExposureProbe
        {
            public function protected(string $baseUrl): ?bool
            {
                return false;
            }
        });

        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);

        $this->actingAs($administrator)
            ->get('/admin/system-health')
            ->assertSuccessful()
            ->assertSee('Internal application protection')
            ->assertSee('publicly accessible')
            ->assertSee('Critical');
    }
}
