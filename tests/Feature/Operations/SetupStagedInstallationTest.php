<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Installation\Contracts\EnvironmentWriter;
use App\Domain\Operations\Installation\EnvironmentWriteResult;
use App\Domain\Operations\Installation\InstallationAttemptStore;
use App\Domain\Operations\Installation\InstallationDatabaseOwnership;
use App\Domain\Operations\Installation\InstallationState;
use App\Domain\Operations\Installation\ResetIncompleteInstallation;
use PDO;
use Tests\TestCase;

final class SetupStagedInstallationTest extends TestCase
{
    private string $databasePath;

    private string $attemptPath;

    private string $markerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = bin2hex(random_bytes(8));
        $this->databasePath = storage_path('framework/testing/setup-staged-'.$suffix.'.sqlite');
        $this->attemptPath = storage_path('framework/testing/setup-staged-'.$suffix.'.json');
        $this->markerPath = storage_path('framework/testing/setup-staged-'.$suffix.'.lock');
        touch($this->databasePath);

        config()->set('waymark.installation.installed', false);
        config()->set('waymark.installation.lock_path', $this->markerPath);
        config()->set('waymark.installation.attempt_path', $this->attemptPath);
        config()->set('session.driver', 'array');
        config()->set('app.env', 'production');
        config()->set('app.debug', false);
        config()->set('waymark.deployment_layout', 'standard');
        $this->app->forgetInstance(InstallationState::class);
        $this->app->forgetInstance(InstallationAttemptStore::class);
        $this->app->instance(EnvironmentWriter::class, new class implements EnvironmentWriter
        {
            public function write(array $values): EnvironmentWriteResult
            {
                return new EnvironmentWriteResult(true, "APP_ENV=production\n", '');
            }
        });
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->attemptPath, $this->attemptPath.'.tmp', $this->markerPath] as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_install_starts_promptly_advances_one_bounded_stage_at_a_time_and_survives_refresh(): void
    {
        $session = $this->completeSetupSession();

        $this->withServerVariables(['DOCUMENT_ROOT' => public_path()])
            ->withSession($session)
            ->post('/setup/install')
            ->assertRedirect('/setup/install/progress');

        self::assertFileExists($this->attemptPath);
        self::assertSame([], $this->tables());
        $attemptId = (new InstallationAttemptStore($this->attemptPath))->load()?->id;

        $this->withServerVariables(['DOCUMENT_ROOT' => public_path()])
            ->post('/setup/install')
            ->assertRedirect('/setup/install/progress');
        self::assertSame($attemptId, (new InstallationAttemptStore($this->attemptPath))->load()?->id);

        $this->get('/setup/install/progress')
            ->assertSuccessful()
            ->assertSee('Preparing configuration')
            ->assertSee('Installation progress');

        $this->postJson('/setup/install/advance')
            ->assertSuccessful()
            ->assertJsonPath('stage', 'checking_database');
        self::assertSame([], $this->tables());

        $this->postJson('/setup/install/advance')
            ->assertSuccessful()
            ->assertJsonPath('stage', 'preparing_database_schema');
        self::assertSame([InstallationDatabaseOwnership::TABLE], $this->tables());

        $this->postJson('/setup/install/advance')
            ->assertSuccessful()
            ->assertJsonPath('stage', 'preparing_database_schema')
            ->assertJsonPath('migration.current', 1);
        self::assertSame(1, (int) $this->pdo()->query('SELECT COUNT(*) FROM migrations')->fetchColumn());

        $this->get('/setup/install/progress')
            ->assertSuccessful()
            ->assertSee('Preparing database schema');

        $completed = false;
        for ($request = 0; $request < 80; $request++) {
            $response = $this->postJson('/setup/install/advance');
            $response->assertSuccessful();

            if ($response->json('completed') === true) {
                $completed = true;
                break;
            }
        }

        self::assertTrue($completed, 'The bounded installation did not complete within the expected number of requests.');
        self::assertFileExists($this->markerPath);
        self::assertNotContains(InstallationDatabaseOwnership::TABLE, $this->tables());
        self::assertSame(1, (int) $this->pdo()->query('SELECT COUNT(*) FROM site_profiles')->fetchColumn());
        self::assertSame(1, (int) $this->pdo()->query('SELECT COUNT(*) FROM users')->fetchColumn());
        $this->get('/setup')->assertNotFound();
    }

    public function test_visible_unrecorded_ddl_is_not_rerun_and_can_follow_the_controlled_reset_path(): void
    {
        $this->withServerVariables(['DOCUMENT_ROOT' => public_path()])
            ->withSession($this->completeSetupSession())
            ->post('/setup/install');
        $this->postJson('/setup/install/advance')->assertSuccessful();
        $this->postJson('/setup/install/advance')->assertSuccessful();

        $pdo = $this->pdo();
        $pdo->exec('CREATE TABLE migrations (id INTEGER PRIMARY KEY AUTOINCREMENT, migration VARCHAR(255) NOT NULL, batch INTEGER NOT NULL)');
        $pdo->exec('CREATE TABLE site_media (id INTEGER PRIMARY KEY, regeneration_cleanup_status VARCHAR(255) NULL)');
        $pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (?, 1)')->execute([
            '2026_08_22_101000_harden_site_media_focal_points',
        ]);

        $store = new InstallationAttemptStore($this->attemptPath);
        $attempt = $store->load();
        self::assertNotNull($attempt);
        $store->save($attempt->fail(
            'database_schema_installation_failed',
            'WM-OBSERVED1',
            'Database schema installation failed.',
            true,
            now()->toIso8601String(),
        ));

        $this->postJson('/setup/install/advance')
            ->assertUnprocessable()
            ->assertJsonPath('diagnostic_id', 'WM-OBSERVED1')
            ->assertJsonPath('failure_category', 'database_schema_installation_failed');
        self::assertContains('regeneration_cleanup_status', $this->columns('site_media'));

        $this->get('/setup/install/progress')
            ->assertSuccessful()
            ->assertSee('WM-OBSERVED1')
            ->assertSee('Reset incomplete installation and retry');

        $this->post('/setup/install/reset', [
            'confirmation' => ResetIncompleteInstallation::CONFIRMATION,
        ])->assertRedirect('/setup/database')
            ->assertSessionHas('status', 'The incomplete Waymark installation was reset. Re-check the database details before retrying.');

        self::assertSame([], $this->tables());
        self::assertFileDoesNotExist($this->attemptPath);
        self::assertSame(3, session('waymark.setup.current_step'));
        self::assertArrayNotHasKey('password', session('waymark.setup.data.database'));
    }

    /** @return array<string, mixed> */
    private function completeSetupSession(): array
    {
        return [
            'waymark.setup.current_step' => 10,
            'waymark.setup.data' => [
                'database' => ['driver' => 'sqlite', 'host' => '', 'port' => null, 'database' => $this->databasePath, 'username' => '', 'password' => ''],
                'group-details' => ['group_name' => 'Peak Pathfinders', 'short_name' => 'PP', 'contact_email' => 'hello@example.test', 'timezone' => 'Europe/London', 'distance_unit' => 'miles', 'ascent_unit' => 'feet'],
                'branding' => ['primary_colour' => '#526B3F', 'accent_colour' => '#D97845', 'typography_option' => 'instrument'],
                'first-administrator' => ['name' => 'Alex Morgan', 'email' => 'alex@example.test', 'password' => 'Correct-Horse-Battery-9'],
                'mail' => ['configured' => true, 'host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'tls', 'username' => 'mailer@example.test', 'password' => 'smtp-secret', 'from_address' => 'hello@example.test', 'test_address' => 'alex@example.test'],
                'modules' => ['enabled' => ['walks', 'socials', 'gallery']],
                'advanced' => ['backup_disk' => 'local', 'release_metadata_url' => '', 'release_public_key_base64' => '', 'recovery_token' => 'Correct-Horse-Battery-Recovery-9!'],
            ],
        ];
    }

    private function pdo(): PDO
    {
        return new PDO('sqlite:'.$this->databasePath, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    /** @return list<string> */
    private function tables(): array
    {
        return array_values(array_map('strval', $this->pdo()
            ->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
            ->fetchAll(PDO::FETCH_COLUMN)));
    }

    /** @return list<string> */
    private function columns(string $table): array
    {
        return array_values(array_map(
            static fn (array $row): string => (string) $row['name'],
            $this->pdo()->query('PRAGMA table_info("'.$table.'")')->fetchAll(PDO::FETCH_ASSOC),
        ));
    }
}
