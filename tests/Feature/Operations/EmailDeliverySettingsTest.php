<?php

namespace Tests\Feature\Operations;

use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Operations\Installation\Contracts\EnvironmentWriter;
use App\Domain\Operations\Installation\Contracts\MailConnectionTester;
use App\Domain\Operations\Installation\EnvironmentWriteResult;
use App\Domain\Operations\Installation\MailConfiguration;
use App\Domain\Operations\Installation\MailConnectionResult;
use App\Filament\Pages\EmailDeliverySettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class EmailDeliverySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_test_and_save_email_delivery_after_installation(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $mailTester = new class implements MailConnectionTester
        {
            public ?MailConfiguration $received = null;

            public function test(MailConfiguration $configuration): MailConnectionResult
            {
                $this->received = $configuration;

                return new MailConnectionResult(true, 'Test message sent.');
            }
        };
        $environmentWriter = new class implements EnvironmentWriter
        {
            /** @var array<string, string> */
            public array $received = [];

            public function write(array $values): EnvironmentWriteResult
            {
                $this->received = $values;

                return new EnvironmentWriteResult(true, '', 'Environment configuration saved.');
            }
        };
        $this->app->instance(MailConnectionTester::class, $mailTester);
        $this->app->instance(EnvironmentWriter::class, $environmentWriter);

        $this->actingAs($administrator);
        Livewire::test(EmailDeliverySettings::class)
            ->fillForm([
                'host' => 'smtp.example.test',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'mailer@example.test',
                'password' => 'smtp-secret',
                'from_address' => 'hello@example.test',
                'test_address' => 'admin@example.test',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        self::assertSame('smtp.example.test', $mailTester->received?->host);
        self::assertSame('true', $environmentWriter->received['WAYMARK_MAIL_CONFIGURED']);
        self::assertSame('smtp', $environmentWriter->received['MAIL_MAILER']);
        self::assertSame('smtp-secret', $environmentWriter->received['MAIL_PASSWORD']);
        self::assertTrue(config('waymark.email.configured'));

        Livewire::test(EmailDeliverySettings::class)
            ->assertFormSet(['password' => null]);
    }

    public function test_email_settings_page_is_available_only_to_administration(): void
    {
        $administrator = User::factory()->create(['role' => AccountRole::Administrator]);
        $member = User::factory()->create(['role' => AccountRole::RegisteredUser]);

        $this->actingAs($administrator)->get('/admin/email-delivery')->assertSuccessful();
        $this->actingAs($member)->get('/admin/email-delivery')->assertForbidden();
    }
}
