<?php

namespace App\Filament\Pages;

use App\Domain\Accounts\Actions\CreateAdministrator;
use App\Domain\Accounts\Actions\PromoteToAdministrator;
use App\Domain\Accounts\Actions\TransferInstallationOwnership;
use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Accounts\Models\InstallationOwnership;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

final class AccountAdministration extends Page
{
    protected static ?string $title = 'Account administration';

    protected static string|\UnitEnum|null $navigationGroup = 'Accounts';

    protected static ?string $navigationLabel = 'Account administration';

    protected static string|array $routeMiddleware = ['sensitive.confirmed'];

    protected string $view = 'filament.pages.account-administration';

    /** @var array<string, mixed> */
    public array $data = ['operation' => 'transfer'];

    public static function getSlug(?Panel $panel = null): string
    {
        return 'account-administration';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasCapability(ModuleCapability::ManageAccounts);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('operation')
                    ->label('High-risk action')
                    ->options([
                        'transfer' => 'Transfer installation ownership',
                        'promote' => 'Promote an existing account',
                        'create' => 'Create an administrator account',
                    ])
                    ->required()
                    ->live(),
                Section::make('Transfer installation ownership')
                    ->description('The new owner must already be an active, verified Administrator.')
                    ->visible(fn (Get $get): bool => $get('operation') === 'transfer')
                    ->schema([
                        Select::make('transfer_to_user_id')
                            ->label('New installation owner')
                            ->options(fn (): array => $this->eligibleAdministrators())
                            ->searchable()
                            ->required(fn (Get $get): bool => $get('operation') === 'transfer'),
                    ]),
                Section::make('Promote an existing account')
                    ->description('The account must be active and have a verified email address.')
                    ->visible(fn (Get $get): bool => $get('operation') === 'promote')
                    ->schema([
                        Select::make('promote_user_id')
                            ->label('Account to promote')
                            ->options(fn (): array => $this->eligibleAccounts())
                            ->searchable()
                            ->required(fn (Get $get): bool => $get('operation') === 'promote'),
                    ]),
                Section::make('Create an administrator account')
                    ->description('The new account receives a password-reset link and must verify its email before it can access administration.')
                    ->visible(fn (Get $get): bool => $get('operation') === 'create')
                    ->schema([
                        TextInput::make('new_administrator_name')
                            ->label('Name')
                            ->maxLength(255)
                            ->required(fn (Get $get): bool => $get('operation') === 'create'),
                        TextInput::make('new_administrator_email')
                            ->label('Email address')
                            ->email()
                            ->maxLength(255)
                            ->required(fn (Get $get): bool => $get('operation') === 'create'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $data = $this->form->getState();

        match ($data['operation']) {
            'transfer' => app(TransferInstallationOwnership::class)->handle(
                $actor,
                User::query()->findOrFail($data['transfer_to_user_id']),
            ),
            'promote' => app(PromoteToAdministrator::class)->handle(
                $actor,
                User::query()->findOrFail($data['promote_user_id']),
            ),
            'create' => app(CreateAdministrator::class)->handle(
                $actor,
                $data['new_administrator_name'],
                $data['new_administrator_email'],
            ),
        };

        Notification::make()
            ->success()
            ->title('Account administration change completed')
            ->send();

        $this->form->fill(['operation' => $data['operation']]);
    }

    /** @return array<int, string> */
    private function eligibleAdministrators(): array
    {
        $ownerId = InstallationOwnership::query()
            ->find(InstallationOwnership::SINGLETON_ID)
            ?->owner_user_id;

        return User::query()
            ->where('account_status', 'active')
            ->whereNotNull('email_verified_at')
            ->where('role', AccountRole::Administrator->value)
            ->when($ownerId !== null, fn ($query) => $query->whereKeyNot($ownerId))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<int, string> */
    private function eligibleAccounts(): array
    {
        return User::query()
            ->where('account_status', 'active')
            ->whereNotNull('email_verified_at')
            ->where(static function ($query): void {
                $query
                    ->whereNull('role')
                    ->orWhere('role', '!=', AccountRole::Administrator->value);
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
