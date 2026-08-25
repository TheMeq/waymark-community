<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Installation\InstallationState;
use App\Http\Controllers\SetupController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class SetupWizardNavigationTest extends TestCase
{
    private string $markerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markerPath = storage_path('framework/testing/setup-navigation-'.bin2hex(random_bytes(8)).'.lock');
        config()->set('waymark.installation.installed', false);
        config()->set('waymark.installation.lock_path', $this->markerPath);
        config()->set('session.driver', 'array');
        $this->app->forgetInstance(InstallationState::class);
    }

    protected function tearDown(): void
    {
        if (is_file($this->markerPath)) {
            unlink($this->markerPath);
        }

        parent::tearDown();
    }

    public function test_welcome_starts_the_ordered_setup_flow(): void
    {
        $this->get('/setup')
            ->assertSuccessful()
            ->assertSee('Set up Waymark Community')
            ->assertSee('Step 1 of 11');

        $this->post('/setup')->assertRedirect('/setup/server-checks');

        $this->get('/setup/server-checks')
            ->assertSuccessful()
            ->assertSee('Server checks')
            ->assertSee('Step 2 of 11');
    }

    public function test_setup_navigation_preserves_a_nested_request_base_path(): void
    {
        URL::forceRootUrl('http://localhost/demo-site/ndwg');
        $request = Request::create('/setup', 'POST');
        $request->setLaravelSession(app('session')->driver());

        $this->assertSame('http://localhost/demo-site/ndwg/setup', route('setup.start'));
        $this->assertSame('http://localhost/demo-site/ndwg/setup/server-checks', route('setup.step.store', 'server-checks'));
        $this->assertSame(
            'http://localhost/demo-site/ndwg/setup/server-checks',
            app(SetupController::class)->start($request)->getTargetUrl(),
        );
    }

    public function test_setup_routes_derive_the_mount_path_from_the_request_before_environment_configuration(): void
    {
        $request = Request::create('http://example.test/demo-site/ndwg/setup', 'GET', [], [], [], [
            'SCRIPT_NAME' => '/demo-site/ndwg/index.php',
            'SCRIPT_FILENAME' => 'C:/shared-hosting/demo-site/ndwg/index.php',
            'PHP_SELF' => '/demo-site/ndwg/index.php',
            'REQUEST_URI' => '/demo-site/ndwg/setup',
        ]);
        app('url')->setRequest($request);

        $this->assertSame('/demo-site/ndwg', $request->getBaseUrl());
        $this->assertSame('http://example.test/demo-site/ndwg/setup', route('setup.start'));
        $this->assertSame('http://example.test/demo-site/ndwg/setup/server-checks', route('setup.step', 'server-checks'));
    }

    public function test_later_steps_cannot_be_opened_before_prerequisites(): void
    {
        $this->get('/setup/first-administrator')->assertRedirect('/setup');
        $this->get('/setup/install')->assertRedirect('/setup');
        $this->get('/setup/health-check')->assertRedirect('/setup');
    }

    #[DataProvider('stepProvider')]
    public function test_each_approved_step_has_a_plain_setup_surface(int $number, string $slug, string $heading): void
    {
        $this->withSession(['waymark.setup.current_step' => $number])
            ->get('/setup/'.$slug)
            ->assertSuccessful()
            ->assertSee($heading)
            ->assertSee("Step {$number} of 11");
    }

    public function test_saved_database_and_mail_passwords_are_never_rendered_back_to_the_browser(): void
    {
        $this->withSession([
            'waymark.setup.current_step' => 3,
            'waymark.setup.data' => [
                'database' => ['host' => 'db.example.test', 'password' => 'database-secret-value'],
                'mail' => ['host' => 'smtp.example.test', 'password' => 'mail-secret-value'],
            ],
        ])->get('/setup/database')
            ->assertSuccessful()
            ->assertSee('db.example.test')
            ->assertDontSee('database-secret-value')
            ->assertDontSee('mail-secret-value');
    }

    /** @return array<string, array{int, string, string}> */
    public static function stepProvider(): array
    {
        return [
            'server compatibility' => [2, 'server-checks', 'Server checks'],
            'database' => [3, 'database', 'Database connection'],
            'group' => [4, 'group-details', 'Group details'],
            'branding' => [5, 'branding', 'Branding preview'],
            'administrator' => [6, 'first-administrator', 'First administrator'],
            'mail' => [7, 'mail', 'Email delivery'],
            'modules' => [8, 'modules', 'Choose modules'],
            'advanced' => [9, 'advanced', 'Advanced services'],
            'install' => [10, 'install', 'Ready to install'],
            'health' => [11, 'health-check', 'Final health check'],
        ];
    }
}
