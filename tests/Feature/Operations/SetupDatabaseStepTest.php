<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Installation\Contracts\DatabaseConnectionTester;
use App\Domain\Operations\Installation\DatabaseConfiguration;
use App\Domain\Operations\Installation\DatabaseConnectionResult;
use App\Domain\Operations\Installation\InstallationState;
use Tests\TestCase;

final class SetupDatabaseStepTest extends TestCase
{
    private string $markerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markerPath = storage_path('framework/testing/setup-database-'.bin2hex(random_bytes(8)).'.lock');
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

    public function test_valid_database_details_are_tested_saved_and_advance_the_wizard(): void
    {
        $this->app->instance(DatabaseConnectionTester::class, new class implements DatabaseConnectionTester
        {
            public ?DatabaseConfiguration $received = null;

            public function test(DatabaseConfiguration $configuration): DatabaseConnectionResult
            {
                $this->received = $configuration;

                return new DatabaseConnectionResult(true, 'Connection successful.');
            }
        });

        $this->withSession(['waymark.setup.current_step' => 3])
            ->post('/setup/database', $this->databasePayload())
            ->assertRedirect('/setup/group-details')
            ->assertSessionHas('waymark.setup.data.database.password', 'database-secret');
    }

    public function test_failed_database_details_are_explained_without_flashing_the_password(): void
    {
        $this->app->instance(DatabaseConnectionTester::class, new class implements DatabaseConnectionTester
        {
            public function test(DatabaseConfiguration $configuration): DatabaseConnectionResult
            {
                return new DatabaseConnectionResult(false, 'Waymark could not connect using those database details.');
            }
        });

        $response = $this->withSession(['waymark.setup.current_step' => 3])
            ->post('/setup/database', $this->databasePayload());

        $response->assertRedirect('/setup/database')
            ->assertSessionHasErrors('database');
        $this->assertArrayNotHasKey('password', session()->getOldInput());
        $this->assertArrayNotHasKey('password_confirmation', session()->getOldInput());
    }

    public function test_database_validation_never_flashes_a_submitted_password(): void
    {
        $response = $this->withSession(['waymark.setup.current_step' => 3])
            ->post('/setup/database', [
                'driver' => 'mysql',
                'password' => 'validation-secret',
            ]);

        $response->assertRedirect('/setup/database')
            ->assertSessionHasErrors(['host', 'database', 'username']);
        $this->assertArrayNotHasKey('password', session()->getOldInput());
    }

    /** @return array<string, string|int> */
    private function databasePayload(): array
    {
        return [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'waymark',
            'username' => 'waymark_user',
            'password' => 'database-secret',
        ];
    }
}
