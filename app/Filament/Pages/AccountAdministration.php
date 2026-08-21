<?php

namespace App\Filament\Pages;

use App\Domain\Accounts\Actions\ApproveAccountDeletion;
use App\Domain\Accounts\Actions\CreateAdministrator;
use App\Domain\Accounts\Actions\PromoteToAdministrator;
use App\Domain\Accounts\Actions\ReviewStaleAccount;
use App\Domain\Accounts\Actions\TransferInstallationOwnership;
use App\Domain\Accounts\Enums\AccountRole;
use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Accounts\Models\AccountDeletionRequest;
use App\Domain\Accounts\Models\InstallationOwnership;
use App\Domain\Accounts\Models\StaleAccountReview;
use App\Domain\Accounts\Security\SensitiveActionAssurance;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
                        'deletion' => 'Review a deletion request',
                        'stale' => 'Review a stale account',
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
                Section::make('Review a deletion request')
                    ->description('Approval disables and anonymises the account while retaining minimal referential history.')
                    ->visible(fn (Get $get): bool => $get('operation') === 'deletion')
                    ->schema([
                        Select::make('deletion_request_id')
                            ->label('Pending deletion request')
                            ->options(fn (): array => $this->pendingDeletionRequests())
                            ->searchable()
                            ->required(fn (Get $get): bool => $get('operation') === 'deletion'),
                        Textarea::make('review_note')->label('Review note')->maxLength(2000),
                    ]),
                Section::make('Review a stale account')
                    ->description('This is a manual review only. Nothing is deleted automatically.')
                    ->visible(fn (Get $get): bool => $get('operation') === 'stale')
                    ->schema([
                        Select::make('stale_account_review_id')
                            ->label('Stale account')
                            ->options(fn (): array => $this->pendingStaleAccounts())
                            ->searchable()
                            ->required(fn (Get $get): bool => $get('operation') === 'stale'),
                        Select::make('stale_decision')
                            ->label('Action')
                            ->options(['leave_alone' => 'Leave alone', 'deactivate' => 'Deactivate account', 'reviewed' => 'Mark reviewed'])
                            ->required(fn (Get $get): bool => $get('operation') === 'stale'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $this->ensureSensitiveAssurance($actor);
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
            'deletion' => app(ApproveAccountDeletion::class)->handle(
                $actor,
                AccountDeletionRequest::query()->findOrFail($data['deletion_request_id']),
                $data['review_note'] ?? null,
            ),
            'stale' => app(ReviewStaleAccount::class)->handle(
                $actor,
                StaleAccountReview::query()->findOrFail($data['stale_account_review_id']),
                $data['stale_decision'],
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
            ->first()
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

    /** @return array<int, string> */
    private function pendingDeletionRequests(): array
    {
        return AccountDeletionRequest::query()->with('user')->where('status', 'requested')->get()
            ->mapWithKeys(fn (AccountDeletionRequest $request): array => [$request->id => $request->user->name.' (#'.$request->id.')'])
            ->all();
    }

    /** @return array<int, string> */
    private function pendingStaleAccounts(): array
    {
        return StaleAccountReview::query()->with('user')->where('status', 'pending')->get()
            ->mapWithKeys(fn (StaleAccountReview $review): array => [$review->id => $review->user->name])
            ->all();
    }

    private function ensureSensitiveAssurance(User $actor): void
    {
        $assurance = app(SensitiveActionAssurance::class);

        $session = app('session.store');

        abort_unless($assurance->hasRecentPasswordConfirmation($actor, $session), 403);
        abort_unless(
            ! $assurance->requiresSecondFactor($actor)
                || $assurance->hasRecentSecondFactorConfirmation($actor, $session),
            403,
        );
    }
}
