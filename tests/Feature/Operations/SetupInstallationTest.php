<?php

namespace Tests\Feature\Operations;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Models\InstallationOwnership;
use App\Domain\Operations\Installation\Contracts\EnvironmentWriter;
use App\Domain\Operations\Installation\EnvironmentWriteResult;
use App\Domain\Operations\Installation\InstallationState;
use App\Domain\Operations\Models\SiteProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SetupInstallationTest extends TestCase
{
    use RefreshDatabase;

    private string $markerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markerPath = storage_path('framework/testing/setup-install-'.bin2hex(random_bytes(8)).'.lock');
        config()->set('waymark.installation.installed', false);
        config()->set('waymark.installation.lock_path', $this->markerPath);
        config()->set('session.driver', 'array');
        config()->set('app.debug', false);
        $this->app->forgetInstance(InstallationState::class);
        $this->app->instance(EnvironmentWriter::class, new class implements EnvironmentWriter
        {
            /** @var array<string, string>|null */
            public ?array $received = null;

            public function write(array $values): EnvironmentWriteResult
            {
                $this->received = $values;

                return new EnvironmentWriteResult(true, "APP_ENV=production\n", '');
            }
        });
    }

    protected function tearDown(): void
    {
        if (is_file($this->markerPath)) {
            unlink($this->markerPath);
        }

        parent::tearDown();
    }

    public function test_install_creates_the_site_and_verified_owner_then_health_completion_locks_setup(): void
    {
        $session = $this->completeSetupSession();

        $this->withSession($session)->post('/setup/install')
            ->assertRedirect('/setup/health-check');

        $profile = SiteProfile::query()->sole();
        $administrator = User::query()->sole();
        $this->assertSame('Peak Pathfinders', $profile->group_name);
        $this->assertSame(['walks' => true, 'socials' => true, 'holidays' => false, 'gallery' => true, 'news' => false, 'documents' => false], $profile->module_configuration);
        $this->assertSame(AccountRole::Administrator, $administrator->role);
        $this->assertNotNull($administrator->email_verified_at);
        $this->assertSame($administrator->id, InstallationOwnership::query()->sole()->owner_user_id);
        $environmentWriter = app(EnvironmentWriter::class);
        $this->assertSame('s3', $environmentWriter->received['WAYMARK_BACKUP_DISK']);
        $this->assertSame('https://objects.example.test', $environmentWriter->received['AWS_ENDPOINT']);
        $this->assertSame('waymark-backups', $environmentWriter->received['AWS_BUCKET']);
        $this->assertSame('backup-key', $environmentWriter->received['AWS_ACCESS_KEY_ID']);
        $this->assertSame('backup-secret', $environmentWriter->received['AWS_SECRET_ACCESS_KEY']);
        $this->assertSame(hash('sha256', 'Correct-Horse-Battery-Recovery-9!'), $environmentWriter->received['WAYMARK_RECOVERY_TOKEN_HASH']);
        $this->assertSame(base64_encode('public-key-fixture'), $environmentWriter->received['WAYMARK_RELEASE_PUBLIC_KEY_BASE64']);

        $this->withSession([...$session, 'waymark.setup.current_step' => 11, 'waymark.setup.installed' => true])
            ->get('/setup/health-check')
            ->assertSuccessful()
            ->assertSee('Database ready')
            ->assertSee('Administrator ready');

        $this->withSession([...$session, 'waymark.setup.current_step' => 11, 'waymark.setup.installed' => true])
            ->post('/setup/health-check')
            ->assertRedirect('/admin');

        $this->assertFileExists($this->markerPath);
        $this->get('/setup')->assertNotFound();
    }

    public function test_environment_write_failure_stops_before_database_install_and_provides_manual_recheck_instructions(): void
    {
        $this->app->instance(EnvironmentWriter::class, new class implements EnvironmentWriter
        {
            public function write(array $values): EnvironmentWriteResult
            {
                return new EnvironmentWriteResult(false, "APP_KEY=base64:manual\nDB_PASSWORD=secret\n", 'Create a file named .env outside the public web root, then re-check.');
            }
        });

        $this->withSession($this->completeSetupSession())
            ->post('/setup/install')
            ->assertRedirect('/setup/install')
            ->assertSessionHasErrors('environment')
            ->assertSessionHas('waymark.setup.environment_file')
            ->assertSessionHas('waymark.setup.environment_instructions');

        $this->assertDatabaseCount('site_profiles', 0);
        $this->assertDatabaseCount('users', 0);
        $this->assertFileDoesNotExist($this->markerPath);
    }

    public function test_install_cannot_run_with_missing_setup_sections(): void
    {
        $this->withSession(['waymark.setup.current_step' => 10])
            ->post('/setup/install')
            ->assertRedirect('/setup/install')
            ->assertSessionHasErrors('install');

        $this->assertDatabaseCount('site_profiles', 0);
        $this->assertDatabaseCount('users', 0);
    }

    /** @return array<string, mixed> */
    private function completeSetupSession(): array
    {
        return [
            'waymark.setup.current_step' => 10,
            'waymark.setup.data' => [
                'database' => ['driver' => 'sqlite', 'host' => '', 'port' => null, 'database' => ':memory:', 'username' => '', 'password' => 'database-secret'],
                'group-details' => ['group_name' => 'Peak Pathfinders', 'short_name' => 'PP', 'contact_email' => 'hello@example.test', 'timezone' => 'Europe/London', 'distance_unit' => 'miles', 'ascent_unit' => 'feet'],
                'branding' => ['primary_colour' => '#526B3F', 'accent_colour' => '#D97845', 'typography_option' => 'instrument'],
                'first-administrator' => ['name' => 'Alex Morgan', 'email' => 'alex@example.test', 'password' => 'Correct-Horse-Battery-9'],
                'mail' => ['host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'tls', 'username' => 'mailer@example.test', 'password' => 'smtp-secret', 'from_address' => 'hello@example.test', 'test_address' => 'alex@example.test'],
                'modules' => ['enabled' => ['walks', 'socials', 'gallery']],
                'advanced' => [
                    'backup_disk' => 's3',
                    'release_metadata_url' => 'https://updates.example.test/stable.json',
                    'release_public_key_base64' => base64_encode('public-key-fixture'),
                    's3_endpoint' => 'https://objects.example.test',
                    's3_bucket' => 'waymark-backups',
                    's3_access_key' => 'backup-key',
                    's3_secret_key' => 'backup-secret',
                    'recovery_token' => 'Correct-Horse-Battery-Recovery-9!',
                ],
            ],
        ];
    }
}
