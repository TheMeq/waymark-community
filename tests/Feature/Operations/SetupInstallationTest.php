<?php

namespace Tests\Feature\Operations;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Models\InstallationOwnership;
use App\Domain\Operations\Installation\Contracts\EnvironmentWriter;
use App\Domain\Operations\Installation\EnvironmentWriteResult;
use App\Domain\Operations\Installation\InstallationAttemptStore;
use App\Domain\Operations\Installation\InstallationState;
use App\Domain\Operations\Models\SiteProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

final class SetupInstallationTest extends TestCase
{
    private string $markerPath;

    private string $attemptPath;

    private string $databasePath;

    private object $environmentWriter;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = bin2hex(random_bytes(8));
        $this->markerPath = storage_path('framework/testing/setup-install-'.$suffix.'.lock');
        $this->attemptPath = storage_path('framework/testing/setup-install-'.$suffix.'.json');
        $this->databasePath = storage_path('framework/testing/setup-install-'.$suffix.'.sqlite');
        touch($this->databasePath);
        config()->set('waymark.installation.installed', false);
        config()->set('waymark.installation.lock_path', $this->markerPath);
        config()->set('waymark.installation.attempt_path', $this->attemptPath);
        config()->set('session.driver', 'array');
        config()->set('app.debug', false);
        $this->app->forgetInstance(InstallationState::class);
        $this->app->forgetInstance(InstallationAttemptStore::class);
        $this->environmentWriter = new class implements EnvironmentWriter
        {
            public bool $fail = false;

            /** @var array<string, string>|null */
            public ?array $received = null;

            public function write(array $values): EnvironmentWriteResult
            {
                $this->received = $values;

                if ($this->fail) {
                    return new EnvironmentWriteResult(false, "APP_ENV=production\n", 'Create the environment file, then retry.');
                }

                return new EnvironmentWriteResult(true, "APP_ENV=production\n", '');
            }
        };
        $this->app->instance(EnvironmentWriter::class, $this->environmentWriter);
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        foreach ([$this->markerPath, $this->attemptPath, $this->attemptPath.'.tmp', $this->databasePath] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_install_creates_the_site_and_verified_owner_then_health_completion_locks_setup(): void
    {
        $session = $this->completeSetupSession();

        $this->withSession($session)->post('/setup/install')
            ->assertRedirect('/setup/install/progress');
        $this->advanceUntilCompleted();

        $profile = SiteProfile::query()->sole();
        $administrator = User::query()->sole();
        $this->assertSame('Peak Pathfinders', $profile->group_name);
        $this->assertSame(['walks' => true, 'socials' => true, 'holidays' => false, 'gallery' => true, 'news' => false, 'documents' => false], $profile->module_configuration);
        $this->assertSame(AccountRole::Administrator, $administrator->role);
        $this->assertNotNull($administrator->email_verified_at);
        $this->assertSame($administrator->id, InstallationOwnership::query()->sole()->owner_user_id);
        $this->assertSame('s3', $this->environmentWriter->received['WAYMARK_BACKUP_DISK']);
        $this->assertSame('https://objects.example.test', $this->environmentWriter->received['AWS_ENDPOINT']);
        $this->assertSame('waymark-backups', $this->environmentWriter->received['AWS_BUCKET']);
        $this->assertSame('backup-key', $this->environmentWriter->received['AWS_ACCESS_KEY_ID']);
        $this->assertSame('backup-secret', $this->environmentWriter->received['AWS_SECRET_ACCESS_KEY']);
        $this->assertSame('waymark-community-session', $this->environmentWriter->received['SESSION_COOKIE']);
        $this->assertSame(hash('sha256', 'Correct-Horse-Battery-Recovery-9!'), $this->environmentWriter->received['WAYMARK_RECOVERY_TOKEN_HASH']);
        $this->assertSame(base64_encode('public-key-fixture'), $this->environmentWriter->received['WAYMARK_RELEASE_PUBLIC_KEY_BASE64']);

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
            ->assertRedirect('/setup/install/progress');

        $failure = $this->postJson('/setup/install/advance')
            ->assertUnprocessable()
            ->assertJsonPath('failure_category', 'configuration_file_could_not_be_written')
            ->assertSessionHas('waymark.setup.environment_file')
            ->assertSessionHas('waymark.setup.environment_instructions')
            ->json();

        $this->get('/setup/install/progress')
            ->assertOk()
            ->assertSee('Preparing configuration failed')
            ->assertSee('Failure category')
            ->assertSee('Configuration file could not be written')
            ->assertSee('No database or application data was changed by this failed step.')
            ->assertSee('Retry installation')
            ->assertSee($failure['diagnostic_id']);

        $this->assertSame([], $this->tables());
        $this->assertFileDoesNotExist($this->markerPath);
    }

    public function test_recoverable_failed_stage_can_be_retried_without_starting_a_second_attempt(): void
    {
        $this->environmentWriter->fail = true;

        $this->withSession($this->completeSetupSession())->post('/setup/install');
        $failure = $this->postJson('/setup/install/advance')
            ->assertUnprocessable()
            ->assertJsonPath('retryable', true)
            ->json();

        $this->environmentWriter->fail = false;

        $this->post('/setup/install/retry')
            ->assertRedirect('/setup/install/progress');

        $retried = $this->get('/setup/install/progress')
            ->assertOk()
            ->assertSee('Waymark is ready to resume this saved installation.')
            ->assertDontSee('Continue installation')
            ->assertDontSee('No database or application data was changed by the failed step.')
            ->assertDontSee('Failure category')
            ->assertDontSee($failure['diagnostic_id']);

        $record = app(InstallationAttemptStore::class)->load();
        self::assertNotNull($record);
        self::assertSame($failure['attempt_id'], $record->id);
        self::assertSame('running', $record->status->value);
        self::assertSame('preparing_configuration', $record->stage->value);

        $this->postJson('/setup/install/advance')->assertSuccessful();
    }

    public function test_install_does_not_use_the_pre_environment_database_cache_configuration(): void
    {
        config()->set('cache.default', 'database');

        $this->withSession($this->completeSetupSession())->post('/setup/install');
        $this->postJson('/setup/install/advance')->assertSuccessful();
        $this->assertSame('array', config('cache.default'));
    }

    public function test_skipped_email_install_writes_an_explicit_disabled_state_without_smtp_credentials(): void
    {
        $session = $this->completeSetupSession();
        $session['waymark.setup.data']['mail'] = ['configured' => false];

        $this->withSession($session)->post('/setup/install')->assertRedirect('/setup/install/progress');
        $this->postJson('/setup/install/advance')->assertSuccessful();

        self::assertSame('false', $this->environmentWriter->received['WAYMARK_MAIL_CONFIGURED']);
        self::assertSame('array', $this->environmentWriter->received['MAIL_MAILER']);
        self::assertSame('', $this->environmentWriter->received['MAIL_HOST']);
        self::assertSame('', $this->environmentWriter->received['MAIL_PASSWORD']);
    }

    public function test_install_cannot_run_with_missing_setup_sections(): void
    {
        $this->withSession(['waymark.setup.current_step' => 10])
            ->post('/setup/install')
            ->assertRedirect('/setup/install')
            ->assertSessionHasErrors('install');

        $this->assertSame([], $this->tables());
    }

    /** @return array<string, mixed> */
    private function completeSetupSession(): array
    {
        return [
            'waymark.setup.current_step' => 10,
            'waymark.setup.data' => [
                'database' => ['driver' => 'sqlite', 'host' => '', 'port' => null, 'database' => $this->databasePath, 'username' => '', 'password' => 'database-secret'],
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

    private function advanceUntilCompleted(): void
    {
        for ($request = 0; $request < 80; $request++) {
            $response = $this->postJson('/setup/install/advance');
            $response->assertSuccessful();

            if ($response->json('completed') === true) {
                return;
            }
        }

        self::fail('The staged installation did not complete.');
    }

    /** @return list<string> */
    private function tables(): array
    {
        $pdo = new PDO('sqlite:'.$this->databasePath, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        return array_values(array_map('strval', $pdo
            ->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
            ->fetchAll(PDO::FETCH_COLUMN)));
    }
}
