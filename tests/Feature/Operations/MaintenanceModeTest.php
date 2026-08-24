<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Maintenance\MaintenanceManager;
use App\Domain\Operations\Models\SiteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class MaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    private string $statePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->statePath = storage_path('framework/testing/maintenance-'.bin2hex(random_bytes(8)).'.json');
        config()->set('waymark.maintenance.state_path', $this->statePath);
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    protected function tearDown(): void
    {
        @unlink($this->statePath);
        parent::tearDown();
    }

    public function test_public_requests_receive_group_branded_503_with_optional_return_and_contact(): void
    {
        SiteProfile::query()->create([
            'group_name' => 'Peak Pathfinders',
            'primary_colour' => '#405B39',
            'accent_colour' => '#D67842',
        ]);
        app(MaintenanceManager::class)->enable(
            'We are applying a carefully checked update.',
            now()->addHour()->startOfMinute(),
            'https://status.example.test/waymark',
        );

        $this->get('/walks')
            ->assertStatus(503)
            ->assertHeader('Retry-After')
            ->assertSee('Peak Pathfinders')
            ->assertSee('We are applying a carefully checked update.')
            ->assertSee('Expected back')
            ->assertSee('https://status.example.test/waymark', false);
    }

    public function test_only_the_generated_privileged_bypass_cookie_can_reach_the_site(): void
    {
        $bypass = app(MaintenanceManager::class)->enable('Short maintenance window.');

        $this->withUnencryptedCookie(MaintenanceManager::BYPASS_COOKIE, 'forged')->get('/robots.txt')->assertStatus(503);
        $this->withUnencryptedCookie(MaintenanceManager::BYPASS_COOKIE, $bypass)->get('/robots.txt')->assertSuccessful();
    }

    public function test_recovery_and_health_entry_points_remain_available_during_maintenance(): void
    {
        app(MaintenanceManager::class)->enable('Recovery work.');

        $this->get('/recovery')->assertSuccessful();
        $this->get('/up')->assertSuccessful();
    }

    public function test_guarded_operation_disables_maintenance_on_success_and_leaves_it_active_on_failure(): void
    {
        $manager = app(MaintenanceManager::class);
        $observedActive = false;
        $manager->run(function () use ($manager, &$observedActive): void {
            $observedActive = $manager->active();
        }, 'Restoring a verified backup.');

        $this->assertTrue($observedActive);
        $this->assertFalse($manager->active());

        try {
            $manager->run(fn () => throw new RuntimeException('controlled failure'), 'Applying an update.');
            $this->fail('The controlled failure did not escape.');
        } catch (RuntimeException $exception) {
            $this->assertSame('controlled failure', $exception->getMessage());
        }

        $this->assertTrue($manager->active());
    }
}
