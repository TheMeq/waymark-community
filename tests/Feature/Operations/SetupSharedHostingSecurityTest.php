<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Installation\InstallationState;
use Tests\TestCase;

final class SetupSharedHostingSecurityTest extends TestCase
{
    private string $markerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markerPath = storage_path('framework/testing/setup-hosting-'.bin2hex(random_bytes(8)).'.lock');
        config()->set('waymark.installation.installed', false);
        config()->set('waymark.installation.lock_path', $this->markerPath);
        config()->set('session.driver', 'array');
        config()->set('app.env', 'production');
        config()->set('app.debug', false);
        $this->app->forgetInstance(InstallationState::class);
    }

    protected function tearDown(): void
    {
        if (is_file($this->markerPath)) {
            unlink($this->markerPath);
        }

        parent::tearDown();
    }

    public function test_server_checks_explain_the_safe_public_only_document_root(): void
    {
        $this->withServerVariables(['DOCUMENT_ROOT' => public_path()])
            ->withSession(['waymark.setup.current_step' => 2])
            ->get('/setup/server-checks')
            ->assertSuccessful()
            ->assertSee('Document root')
            ->assertSee('Environment file exposure')
            ->assertSee('Production debug mode');
    }

    public function test_install_completion_rechecks_and_blocks_an_exposed_application_layout(): void
    {
        $this->withServerVariables(['DOCUMENT_ROOT' => base_path()])
            ->withSession(['waymark.setup.current_step' => 10])
            ->post('/setup/install')
            ->assertRedirect('/setup/install')
            ->assertSessionHasErrors('hosting');
    }

    public function test_install_completion_rechecks_and_blocks_production_debug_mode(): void
    {
        config()->set('app.debug', true);

        $this->withServerVariables(['DOCUMENT_ROOT' => public_path()])
            ->withSession(['waymark.setup.current_step' => 10])
            ->post('/setup/install')
            ->assertRedirect('/setup/install')
            ->assertSessionHasErrors('hosting');
    }

    public function test_shared_host_deployment_guidance_is_generic_and_keeps_secrets_outside_public_html(): void
    {
        $guide = (string) file_get_contents(base_path('docs/deployment/shared-hosting.md'));

        $this->assertStringContainsString('public_html', $guide);
        $this->assertStringContainsString('outside', strtolower($guide));
        $this->assertStringContainsString('document root', strtolower($guide));
        $this->assertStringNotContainsString('C:\\Users\\', $guide);
        $this->assertStringNotContainsString('/home/richa/', $guide);

        $frontController = (string) file_get_contents(public_path('index.php'));
        $this->assertStringContainsString('WAYMARK_APPLICATION_ROOT', $frontController);
    }
}
