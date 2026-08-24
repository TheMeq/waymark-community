<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Maintenance\MaintenanceManager;
use App\Domain\Operations\Updates\UpdateStateStore;
use Tests\TestCase;

final class UpdateActivationPageTest extends TestCase
{
    private string $statePath;

    private string $maintenancePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->statePath = storage_path('framework/testing/update-activation-page-'.bin2hex(random_bytes(8)).'.json');
        $this->maintenancePath = $this->statePath.'.maintenance';
        config()->set('waymark.updates.state_path', $this->statePath);
        config()->set('waymark.maintenance.state_path', $this->maintenancePath);
        app(UpdateStateStore::class)->write([
            'status' => 'pending_activation',
            'pending_version' => '1.2.0',
            'activation_token_hash' => hash('sha256', 'fresh-request-token'),
        ]);
        app(MaintenanceManager::class)->enable('Waymark is applying a verified update.');
    }

    protected function tearDown(): void
    {
        @unlink($this->statePath);
        @unlink($this->maintenancePath);
        parent::tearDown();
    }

    public function test_pending_activation_page_is_reachable_during_maintenance_only_with_its_token(): void
    {
        $this->get('/updates/activate?token=wrong')->assertNotFound();

        $this->get('/updates/activate?token=fresh-request-token')
            ->assertSuccessful()
            ->assertSee('Finish updating to Waymark Community 1.2.0')
            ->assertSee('fresh-request-token', false)
            ->assertSee('Complete update');
    }
}
