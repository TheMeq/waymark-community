<?php

namespace Tests\Feature\Operations;

use App\Domain\Operations\Installation\Contracts\DatabaseConnectionTester;
use App\Domain\Operations\Installation\Contracts\MailConnectionTester;
use App\Domain\Operations\Installation\DatabaseConfiguration;
use App\Domain\Operations\Installation\DatabaseConnectionResult;
use App\Domain\Operations\Installation\InstallationState;
use App\Domain\Operations\Installation\MailConfiguration;
use App\Domain\Operations\Installation\MailConnectionResult;
use Tests\TestCase;

final class SetupConfigurationStepsTest extends TestCase
{
    private string $markerPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markerPath = storage_path('framework/testing/setup-configuration-'.bin2hex(random_bytes(8)).'.lock');
        config()->set('waymark.installation.installed', false);
        config()->set('waymark.installation.lock_path', $this->markerPath);
        config()->set('session.driver', 'array');
        $this->app->forgetInstance(InstallationState::class);
        $this->app->instance(DatabaseConnectionTester::class, new class implements DatabaseConnectionTester
        {
            public function test(DatabaseConfiguration $configuration): DatabaseConnectionResult
            {
                return new DatabaseConnectionResult(true, 'Connection successful.');
            }
        });
        $this->app->instance(MailConnectionTester::class, new class implements MailConnectionTester
        {
            public function test(MailConfiguration $configuration): MailConnectionResult
            {
                return new MailConnectionResult(true, 'Test message sent.');
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

    public function test_group_branding_administrator_mail_module_and_advanced_steps_persist_validated_configuration(): void
    {
        $this->withSession(['waymark.setup.current_step' => 4])
            ->post('/setup/group-details', [
                'group_name' => 'Peak Pathfinders',
                'short_name' => 'PP',
                'contact_email' => 'hello@example.test',
                'timezone' => 'Europe/London',
                'distance_unit' => 'miles',
                'ascent_unit' => 'feet',
            ])->assertRedirect('/setup/branding')
            ->assertSessionHas('waymark.setup.data.group-details.group_name', 'Peak Pathfinders');

        $this->withSession(['waymark.setup.current_step' => 5])
            ->post('/setup/branding', [
                'primary_colour' => '#526B3F',
                'accent_colour' => '#D97845',
                'typography_option' => 'instrument',
            ])->assertRedirect('/setup/first-administrator');

        $this->withSession(['waymark.setup.current_step' => 6])
            ->post('/setup/first-administrator', [
                'name' => 'Alex Morgan',
                'email' => 'alex@example.test',
                'password' => 'Correct-Horse-Battery-9',
                'password_confirmation' => 'Correct-Horse-Battery-9',
            ])->assertRedirect('/setup/mail')
            ->assertSessionHas('waymark.setup.data.first-administrator.email', 'alex@example.test')
            ->assertSessionMissing('waymark.setup.data.first-administrator.password_confirmation');

        $this->withSession(['waymark.setup.current_step' => 7])
            ->post('/setup/mail', [
                'host' => 'smtp.example.test',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'mailer@example.test',
                'password' => 'smtp-secret',
                'from_address' => 'hello@example.test',
                'test_address' => 'alex@example.test',
            ])->assertRedirect('/setup/modules')
            ->assertSessionHas('waymark.setup.data.mail.password', 'smtp-secret');

        $this->withSession(['waymark.setup.current_step' => 8])
            ->post('/setup/modules', [
                'modules' => ['walks', 'socials', 'holidays', 'gallery', 'news', 'documents'],
            ])->assertRedirect('/setup/advanced')
            ->assertSessionHas('waymark.setup.data.modules.enabled', ['walks', 'socials', 'holidays', 'gallery', 'news', 'documents']);

        $this->withSession(['waymark.setup.current_step' => 9])
            ->post('/setup/advanced', [
                'backup_disk' => 'local',
                'release_metadata_url' => 'https://updates.example.test/stable.json',
                'release_public_key_base64' => base64_encode('public-key-fixture'),
                'recovery_token' => 'Correct-Horse-Battery-Recovery-9!',
                'recovery_token_confirmation' => 'Correct-Horse-Battery-Recovery-9!',
            ])->assertRedirect('/setup/install')
            ->assertSessionHas('waymark.setup.data.advanced.backup_disk', 'local')
            ->assertSessionHas('waymark.setup.data.advanced.recovery_token', 'Correct-Horse-Battery-Recovery-9!')
            ->assertSessionMissing('waymark.setup.data.advanced.recovery_token_confirmation');
    }

    public function test_password_validation_errors_do_not_flash_first_admin_or_mail_secrets(): void
    {
        $this->withSession(['waymark.setup.current_step' => 6])
            ->post('/setup/first-administrator', [
                'name' => 'Alex Morgan',
                'email' => 'alex@example.test',
                'password' => 'one-secret-value',
                'password_confirmation' => 'different-secret-value',
            ])->assertRedirect('/setup/first-administrator')
            ->assertSessionHasErrors('password');

        $this->assertArrayNotHasKey('password', session()->getOldInput());
        $this->assertArrayNotHasKey('password_confirmation', session()->getOldInput());

        $this->withSession(['waymark.setup.current_step' => 7])
            ->post('/setup/mail', [
                'password' => 'smtp-validation-secret',
            ])->assertRedirect('/setup/mail')
            ->assertSessionHasErrors(['host', 'port', 'from_address', 'test_address']);

        $this->assertArrayNotHasKey('password', session()->getOldInput());
    }

    public function test_s3_backup_destination_requires_complete_connection_details(): void
    {
        $this->withSession(['waymark.setup.current_step' => 9])
            ->post('/setup/advanced', ['backup_disk' => 's3'])
            ->assertRedirect('/setup/advanced')
            ->assertSessionHasErrors(['s3_endpoint', 's3_bucket', 's3_access_key', 's3_secret_key']);
    }

    public function test_recovery_token_must_be_strong_and_confirmed_without_flashing_the_secret(): void
    {
        $this->withSession(['waymark.setup.current_step' => 9])
            ->post('/setup/advanced', [
                'backup_disk' => 'local',
                'recovery_token' => 'short',
                'recovery_token_confirmation' => 'different-secret',
            ])
            ->assertRedirect('/setup/advanced')
            ->assertSessionHasErrors('recovery_token');

        $this->assertArrayNotHasKey('recovery_token', session()->getOldInput());
        $this->assertArrayNotHasKey('recovery_token_confirmation', session()->getOldInput());
    }

    public function test_branding_step_previews_the_group_identity_without_changing_the_public_site(): void
    {
        $this->withSession([
            'waymark.setup.current_step' => 5,
            'waymark.setup.data' => [
                'group-details' => ['group_name' => 'Peak Pathfinders'],
                'branding' => ['primary_colour' => '#445E3B', 'accent_colour' => '#D97845'],
            ],
        ])->get('/setup/branding')
            ->assertSuccessful()
            ->assertSee('Peak Pathfinders')
            ->assertSee('#445E3B');
    }
}
