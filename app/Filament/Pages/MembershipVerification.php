<?php

namespace App\Filament\Pages;

use App\Domain\Accounts\Actions\RecordMembershipVerification;
use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Membership\Enums\MembershipStatus;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class MembershipVerification extends Page
{
    protected static ?string $title = 'Membership verification';

    protected static string|\UnitEnum|null $navigationGroup = 'Accounts';

    protected static ?string $navigationLabel = 'Membership verification';

    protected string $view = 'filament.pages.membership-verification';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function getSlug(?Panel $panel = null): string
    {
        return 'membership-verification';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasCapability(ModuleCapability::ManageMembershipVerification);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Verification record')->schema([
                    Select::make('account_id')
                        ->label('Account')
                        ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (?string $state): void {
                            $this->loadCurrentVerificationRecord($state);
                        })
                        ->required(),
                    Select::make('status')
                        ->options(collect(MembershipStatus::cases())->mapWithKeys(fn (MembershipStatus $status): array => [
                            $status->value => str($status->value)->replace('_', ' ')->title()->toString(),
                        ])->all())
                        ->required(),
                    TextInput::make('source')
                        ->label('Source or method')
                        ->maxLength(255),
                    DatePicker::make('review_due_at')
                        ->label('Review due date'),
                ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $data = $this->form->getState();
        $account = User::query()->findOrFail($data['account_id']);

        app(RecordMembershipVerification::class)->handle(
            $actor,
            $account,
            $data['status'],
            $data['source'] ?? null,
            $data['review_due_at'] ?? null,
        );

        Notification::make()
            ->success()
            ->title('Membership verification saved')
            ->send();
    }

    private function loadCurrentVerificationRecord(?string $accountId): void
    {
        $account = User::query()->find($accountId);

        if (! $account instanceof User) {
            return;
        }

        $this->data['status'] = $account->membership_status->value;
        $this->data['source'] = $account->membership_verification_source;
        $this->data['review_due_at'] = $account->membership_review_due_at?->toDateString();
    }
}
