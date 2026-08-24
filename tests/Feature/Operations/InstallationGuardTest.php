<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Installation\InstallationState;
use Tests\TestCase;

final class InstallationGuardTest extends TestCase
{
    private string $markerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markerPath = storage_path('framework/testing/installation-'.bin2hex(random_bytes(8)).'.lock');
    }

    protected function tearDown(): void
    {
        if (is_file($this->markerPath)) {
            unlink($this->markerPath);
        }

        parent::tearDown();
    }

    public function test_uninstalled_requests_are_routed_to_setup_before_database_middleware_runs(): void
    {
        $this->configureInstallation(false);
        config()->set('database.default', 'database-that-does-not-exist');

        $this->get('/')->assertRedirect('/setup');
        $this->get('/not-a-real-public-route')->assertRedirect('/setup');
        $this->post('/contact')->assertRedirect('/setup');
    }

    public function test_setup_is_the_only_application_surface_available_before_installation(): void
    {
        $this->configureInstallation(false);

        $this->get('/setup')
            ->assertSuccessful()
            ->assertSee('Set up Waymark Community')
            ->assertSee('Installation has not started');
    }

    public function test_setup_is_locked_after_installation(): void
    {
        $this->configureInstallation(true);

        $this->get('/setup')->assertNotFound();
    }

    public function test_the_completion_marker_locks_setup_without_database_state(): void
    {
        $this->configureInstallation(false);
        app(InstallationState::class)->complete();

        $this->get('/setup')->assertNotFound();
    }

    private function configureInstallation(bool $installed): void
    {
        config()->set('waymark.installation.installed', $installed ? true : false);
        config()->set('waymark.installation.lock_path', $this->markerPath);
        $this->app->forgetInstance(InstallationState::class);
    }
}
