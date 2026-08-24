<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Installation\InstallationState;
use Tests\TestCase;

final class SetupServerChecksTest extends TestCase
{
    private string $markerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markerPath = storage_path('framework/testing/setup-server-'.bin2hex(random_bytes(8)).'.lock');
        config()->set('waymark.installation.installed', false);
        config()->set('waymark.installation.lock_path', $this->markerPath);
        config()->set('session.driver', 'array');
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

    public function test_server_check_results_are_explained_and_a_supported_server_can_continue(): void
    {
        $session = ['waymark.setup.current_step' => 2];

        $this->withSession($session)->get('/setup/server-checks')
            ->assertSuccessful()
            ->assertSee('PHP version')
            ->assertSee('Required extensions')
            ->assertSee('Writable folders')
            ->assertSee('Image processing')
            ->assertSee('HTTPS')
            ->assertSee('Scheduled tasks');

        $this->withSession($session)->post('/setup/server-checks')
            ->assertRedirect('/setup/database');
    }

    public function test_a_blocking_server_failure_cannot_be_ignored(): void
    {
        config()->set('app.debug', true);

        $this->withSession(['waymark.setup.current_step' => 2])
            ->post('/setup/server-checks')
            ->assertRedirect('/setup/server-checks')
            ->assertSessionHasErrors('server');
    }
}
