<?php

namespace App\Filament\Pages;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Operations\Installation\Contracts\EnvironmentWriter;
use App\Domain\Operations\Installation\Contracts\MailConnectionTester;
use App\Domain\Operations\Installation\MailConfiguration;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Schema;

final class EmailDeliverySettings extends Page
{
    protected static ?string $title = 'Email delivery';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Email delivery';

    protected string $view = 'filament.pages.email-delivery-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function getSlug(?Panel $panel = null): string
    {
        return 'email-delivery';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasCapability(ModuleCapability::ManageAccounts);
    }

    public function mount(): void
    {
        $this->form->fill([
            'host' => (string) config('mail.mailers.smtp.host'),
            'port' => (int) config('mail.mailers.smtp.port', 587),
            'encryption' => config('mail.mailers.smtp.scheme') ?: null,
            'username' => config('mail.mailers.smtp.username'),
            'password' => null,
            'from_address' => (string) config('mail.from.address'),
            'test_address' => (string) auth()->user()?->email,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('host')->label('SMTP host')->required()->maxLength(255),
            TextInput::make('port')->numeric()->required()->minValue(1)->maxValue(65535),
            Select::make('encryption')->options(['tls' => 'TLS', 'ssl' => 'SSL', '' => 'None'])->nullable(),
            TextInput::make('username')->maxLength(255)->autocomplete('username'),
            TextInput::make('password')->password()->maxLength(1024)->autocomplete('new-password'),
            TextInput::make('from_address')->label('From address')->email()->required()->maxLength(255),
            TextInput::make('test_address')->label('Send test to')->email()->required()->maxLength(255),
        ])->statePath('data');
    }

    public function save(): void
    {
        $configuration = MailConfiguration::fromArray($this->form->getState());
        $test = app(MailConnectionTester::class)->test($configuration);

        if (! $test->successful) {
            Notification::make()->danger()->title($test->message)->send();

            return;
        }

        $written = app(EnvironmentWriter::class)->write([
            'WAYMARK_MAIL_CONFIGURED' => 'true',
            'MAIL_MAILER' => 'smtp',
            'MAIL_HOST' => $configuration->host,
            'MAIL_PORT' => (string) $configuration->port,
            'MAIL_ENCRYPTION' => (string) ($configuration->encryption ?? ''),
            'MAIL_USERNAME' => (string) ($configuration->username ?? ''),
            'MAIL_PASSWORD' => (string) ($configuration->password ?? ''),
            'MAIL_FROM_ADDRESS' => $configuration->fromAddress,
        ]);

        if (! $written->written) {
            Notification::make()->danger()->title('Waymark could not save the email configuration file.')->send();

            return;
        }

        config()->set('waymark.email.configured', true);
        config()->set('mail.default', 'smtp');
        config()->set('mail.mailers.smtp.host', $configuration->host);
        config()->set('mail.mailers.smtp.port', $configuration->port);
        config()->set('mail.mailers.smtp.scheme', $configuration->encryption);
        config()->set('mail.mailers.smtp.username', $configuration->username);
        config()->set('mail.mailers.smtp.password', $configuration->password);
        config()->set('mail.from.address', $configuration->fromAddress);
        $this->data['password'] = null;

        Notification::make()->success()->title('Email delivery tested and saved')->send();
    }
}
